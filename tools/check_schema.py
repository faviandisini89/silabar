#!/usr/bin/env python3
"""Fail if the Python analytics schema uses a column the Laravel migrations do not create."""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKIP = {"index", "unique", "primary", "foreign", "dropColumn", "dropIndex", "dropUnique", "dropForeign", "constrained"}
BASE_USERS = {"id", "name", "email", "email_verified_at", "password", "remember_token", "created_at", "updated_at"}  # Laravel skeleton


def migration_columns() -> dict[str, set[str]]:
    tables: dict[str, set[str]] = {"users": set(BASE_USERS)}
    for f in sorted((ROOT / "database/migrations").glob("*.php")):
        cur = None
        in_down = False
        for line in f.read_text().splitlines():
            if "function down" in line:
                in_down = True
            if in_down:
                break
            m = re.search(r"Schema::(?:create|table)\('(\w+)'", line)
            if m:
                cur = m.group(1)
                tables.setdefault(cur, set())
                continue
            if not cur:
                continue
            if re.search(r"\$table->id\(\)", line):
                tables[cur].add("id")
            if "$table->timestamps()" in line:
                tables[cur] |= {"created_at", "updated_at"}
            for typ, name in re.findall(r"\$table->(\w+)\(\s*'([^']+)'", line):
                if typ not in SKIP:
                    tables[cur].add(name)
            for names in re.findall(r"\$table->dropColumn\(\[([^\]]*)\]", line):
                pass
    return tables


def python_columns() -> dict[str, set[str]]:
    out = {}
    sql = (ROOT / "analytics/silabar_analytics/schema.sql").read_text()
    for m in re.finditer(r"CREATE TABLE (\w+) \((.*?)\);", sql, re.S):
        cols = {c.strip().split()[0] for c in m.group(2).split(",")}
        out[m.group(1)] = cols
    return out


def main() -> int:
    mig, py = migration_columns(), python_columns()
    bad = 0
    for table, cols in py.items():
        if table not in mig:
            print(f"MISSING TABLE  {table}")
            bad += 1
            continue
        for c in sorted(cols - mig[table]):
            print(f"MISSING COLUMN {table}.{c}")
            bad += 1
    print(f"{len(py)} tables checked, {bad} problem(s)")
    return 1 if bad else 0


if __name__ == "__main__":
    sys.exit(main())
