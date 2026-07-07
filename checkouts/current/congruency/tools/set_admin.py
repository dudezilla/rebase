#!/usr/bin/env python3
"""set_admin.py -- provision (or rotate) an admin login in a CMS database.

Explicit + safe: you name the DB and login; the password is either given (--password) or generated
(--generate, printed once). Creates the auth tables if missing and replaces any existing entry for that
login. Use it to inject a login at deploy time, or to rotate the demo admin -- instead of shipping a
credential in git. (Stored plaintext: the 2006 UserPrivilegeSet auth compares the password plaintext.)

    python3 tools/set_admin.py --db state/congruency.sqlite --login admin --generate
    python3 tools/set_admin.py --db /srv/site/state/congruency.sqlite --login ops --password 's3cret'

python-only, standalone (operates on the --db you name; no registry needed).
"""
import argparse
import os
import secrets
import sqlite3
import string
import sys


def gen_password(n=16):
    alphabet = string.ascii_letters + string.digits
    return "".join(secrets.choice(alphabet) for _ in range(n))


def set_admin(db, login, password, group_id=1):
    c = sqlite3.connect(db)
    try:
        c.execute("CREATE TABLE IF NOT EXISTS Login_Password (Login TEXT, Password TEXT)")
        c.execute("CREATE TABLE IF NOT EXISTS User_Group_Mappings (Login TEXT, Group_ID INTEGER)")
        c.execute("CREATE TABLE IF NOT EXISTS Group_Privileges (Group_ID INTEGER, Module TEXT, Value TEXT)")
        c.execute("DELETE FROM Login_Password WHERE Login=?", (login,))
        c.execute("DELETE FROM User_Group_Mappings WHERE Login=?", (login,))
        c.execute("INSERT INTO Login_Password (Login, Password) VALUES (?,?)", (login, password))
        c.execute("INSERT INTO User_Group_Mappings (Login, Group_ID) VALUES (?,?)", (login, group_id))
        if not c.execute("SELECT 1 FROM Group_Privileges WHERE Group_ID=?", (group_id,)).fetchone():
            c.execute("INSERT INTO Group_Privileges (Group_ID, Module, Value) VALUES (?,'admin','1')", (group_id,))
        c.commit()
    finally:
        c.close()


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--db", required=True, help="target SQLite DB")
    ap.add_argument("--login", required=True, help="admin login name")
    ap.add_argument("--password", help="admin password (plaintext)")
    ap.add_argument("--generate", action="store_true", help="generate a random password and print it once")
    a = ap.parse_args()

    if not a.password and not a.generate:
        sys.stderr.write("set_admin: pass --password <P> or --generate\n")
        return 2
    if not os.path.isfile(a.db):
        sys.stderr.write("set_admin: no DB at %s\n" % a.db)
        return 2

    pw = a.password or gen_password()
    set_admin(a.db, a.login, pw)
    print("set_admin: admin login %r set on %s" % (a.login, a.db))
    if a.generate:
        print("  password: %s   (shown once -- save it now)" % pw)
    return 0


if __name__ == "__main__":
    sys.exit(main())
