# rebase — congruency CMS, containerized.
# The web server is NOT bundled: `setup.py install` provisions a static PHP 8 build at BUILD time
# (needs network), installs the crank's db, and verifies. The container then serves in the
# FOREGROUND, config-object-driven (port/host from install.json). Separate config from code:
# nothing here hard-codes the port — the configuration object drives it.
FROM python:3.13-slim

RUN apt-get update \
 && apt-get install -y --no-install-recommends git ca-certificates \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .

# checkout newest version-* tag (detached) + provision static php + install db (from the in-crank
# database.tar.xz) + verify. Requires the .git dir (tags) and network for the php download.
RUN python3 setup.py install

EXPOSE 8899

# Foreground server = the container's main process. Port/host come from the configuration object
# (install.json); override with `docker run … python3 setup.py up --foreground --port 9000`.
CMD ["python3", "setup.py", "up", "--foreground"]
