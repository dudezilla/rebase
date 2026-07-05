"""gitutil.py — instrumented git plumbing shared by the versioning scripts.

Principle: NO GIT OPERATION IS DONE FROM MEMORY.
  * Every operation is a real `git` subprocess executed against live state — the
    scripts never assume, cache, or infer repository state.
  * Every operation is recorded (command, cwd, rc, output) to an append-only log
    and (optionally) echoed. That record IS the instrumentation.
  * Write operations REQUIRE an explicit identity passed in by the caller; git's
    ambient/global user.* config is never trusted for authorship.
"""
import json
import os
import subprocess
import time


class GitError(Exception):
    pass


class Git:
    def __init__(self, identity=None, logfile=None, echo=True):
        # identity: {"name":..., "email":...} — mandatory for any write op.
        self.identity = identity
        self.logfile = logfile
        self.echo = echo
        self.ops = []
        if logfile:
            # truncate a fresh instrumentation log for this run
            with open(logfile, "w") as fh:
                fh.write(f"# git instrumentation log — {time.strftime('%Y-%m-%dT%H:%M:%S')}\n")

    def _record(self, rec):
        self.ops.append(rec)
        line = "[git] %-28s %s -> rc=%d" % (
            os.path.basename(rec["cwd"].rstrip("/")) or "/",
            " ".join(rec["argv"][1:]),
            rec["rc"],
        )
        if self.echo:
            print(line)
        if self.logfile:
            with open(self.logfile, "a") as fh:
                fh.write(json.dumps(rec) + "\n")

    def run(self, args, cwd, write=False, check=True, strip=True):
        argv = ["git"]
        env = dict(os.environ)
        if write:
            if not self.identity:
                raise GitError("refusing write op without an explicit identity "
                               "(no git operations from memory)")
            argv += ["-c", "user.name=%s" % self.identity["name"],
                     "-c", "user.email=%s" % self.identity["email"]]
            env["GIT_AUTHOR_NAME"] = env["GIT_COMMITTER_NAME"] = self.identity["name"]
            env["GIT_AUTHOR_EMAIL"] = env["GIT_COMMITTER_EMAIL"] = self.identity["email"]
        argv += list(args)
        proc = subprocess.run(argv, cwd=cwd, env=env,
                              capture_output=True, text=True)
        rec = {
            "ts": time.strftime("%Y-%m-%dT%H:%M:%S"),
            "cwd": cwd, "argv": argv, "write": write,
            "rc": proc.returncode,
            "stdout": proc.stdout.strip(),
            "stderr": proc.stderr.strip(),
        }
        self._record(rec)
        if check and proc.returncode != 0:
            raise GitError("git %s failed in %s: %s"
                           % (" ".join(args), cwd, proc.stderr.strip()))
        return proc.stdout.strip() if strip else proc.stdout.rstrip("\n")

    # read-only convenience: returns stdout or None on failure (still logged)
    def query(self, args, cwd, strip=True):
        try:
            return self.run(args, cwd, write=False, check=False, strip=strip)
        except GitError:
            return None


# ------------------------------------------------------------------ discovery --
GENERATED = {"tournament-lineage", "tournament-package"}  # excluded from versioning by default


def is_git_repo(path):
    return os.path.isdir(os.path.join(path, ".git")) or os.path.isfile(os.path.join(path, ".git"))


def repo_root_of(path, git):
    top = git.query(["rev-parse", "--show-toplevel"], path)
    return top or None


def discover_top_repos(root, git, include_generated=False):
    repos = []
    for name in sorted(os.listdir(root)):
        p = os.path.join(root, name)
        if not os.path.isdir(p) or not is_git_repo(p):
            continue
        if not include_generated and name in GENERATED:
            continue
        repos.append(p)
    return repos


def parse_gitmodules(path, git):
    """Return {name: {'path':..., 'url':...}} from .gitmodules, or {}."""
    gm = os.path.join(path, ".gitmodules")
    if not os.path.isfile(gm):
        return {}
    raw = git.query(["config", "-f", ".gitmodules", "--list"], path) or ""
    mods = {}
    for line in raw.splitlines():
        if "=" not in line:
            continue
        key, val = line.split("=", 1)
        # submodule.<name>.<attr>
        parts = key.split(".")
        if len(parts) >= 3 and parts[0] == "submodule":
            name = ".".join(parts[1:-1])
            attr = parts[-1]
            mods.setdefault(name, {})[attr] = val
    return mods


def submodule_status(path, git):
    """Parse `git submodule status` -> list of dicts with status char, sha, subpath."""
    raw = git.query(["submodule", "status"], path, strip=False)
    out = []
    if not raw:
        return out
    for line in raw.splitlines():
        if not line.strip():
            continue
        flag = line[0] if line[0] in " +-U" else " "
        rest = line[1:] if line[0] in " +-U" else line
        sha = rest.split(" ", 1)[0]
        tail = rest[len(sha):].strip()
        subpath = tail.split(" ")[0] if tail else ""
        out.append({"flag": flag, "sha": sha, "subpath": subpath})
    return out
