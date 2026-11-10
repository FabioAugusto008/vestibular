"""Install only estudai_teste_2026 from reviewed structure and static catalogs.

No .env is read. The administrative credential is supplied in memory through
ESTUDAI_BOOTSTRAP_PASSWORD by a protected local prompt. Default: validate only.
Existing schemas are always refused, including a partially completed install.
"""

import argparse
import hashlib
import json
import os
import re
import subprocess
from pathlib import Path

TARGET = "estudai_teste_2026"
MYSQL = Path(r"C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe")
ROOT = Path(__file__).resolve().parent
IDENTIFIER = re.compile(r"^[a-z0-9_]+$")
CATALOGS = ("materias", "conteudos", "conquistas", "questoes", "questoes_alternativas")


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def quoted(name):
    require(bool(IDENTIFIER.fullmatch(name)), "Invalid identifier")
    return "`" + name + "`"


def qualified(name):
    return quoted(TARGET) + "." + quoted(name)


def literal(value):
    # Hex literals avoid SQL-mode-dependent string escaping.
    if value is None:
        return "NULL"
    if isinstance(value, bool):
        return "1" if value else "0"
    if isinstance(value, (int, float)):
        require(re.fullmatch(r"-?\d+(?:\.\d+)?", str(value)), "Invalid numeric value")
        return str(value)
    require(isinstance(value, str), "Catalog values must be scalar")
    if value == "":
        return "''"
    return "CONVERT(0x" + value.encode("utf-8").hex() + " USING utf8mb4)"


def validate_fixtures():
    manifest = json.loads((ROOT / "manifest_stage06.json").read_text(encoding="utf-8"))
    require(manifest["target"] == TARGET, "Unexpected fixture destination")
    for name, digest in manifest["sha256"].items():
        require(name in ("structure_stage06.json", "catalog_stage06.json"), "Unknown fixture")
        require(hashlib.sha256((ROOT / name).read_bytes()).hexdigest() == digest, "Fixture hash mismatch")
    structure = json.loads((ROOT / "structure_stage06.json").read_text(encoding="utf-8"))
    catalog = json.loads((ROOT / "catalog_stage06.json").read_text(encoding="utf-8"))
    require(len(structure) == 42 and len({x["name"] for x in structure}) == 42, "Expected 42 unique tables")
    require(set(catalog) == set(CATALOGS), "Only static catalogs may be imported")
    require({k: len(v) for k, v in catalog.items()} == manifest["catalog_counts"], "Catalog count mismatch")
    require(len(catalog["questoes"]) == len({x["enunciado"] for x in catalog["questoes"]}) == 32, "Question duplication")
    ids = {q["id"] for q in catalog["questoes"]}
    for question_id in ids:
        alternatives = [a for a in catalog["questoes_alternativas"] if a["questao_id"] == question_id]
        require(len(alternatives) == 5 and {a["letra"] for a in alternatives} == set("ABCDE"), "Invalid alternatives")
        require(sum(int(a["correta"]) for a in alternatives) == 1, "Invalid answer key")
    require(all(a["questao_id"] in ids for a in catalog["questoes_alternativas"]), "Orphan alternative")
    creates, constraints = [], []
    table_names = {x["name"] for x in structure}
    column_list = r"\(`[a-z0-9_]+`(?:, `[a-z0-9_]+`)*\)"
    for table in structure:
        name, ddl = table["name"], table["ddl"]
        quoted(name)
        # SHOW CREATE output is one CREATE TABLE, never a dump or executable routine.
        require(ddl.startswith("CREATE TABLE " + quoted(name) + " ("), "Unexpected DDL prefix")
        require(";" not in ddl and "DEFINER" not in ddl, "Unsafe DDL")
        lines, foreign_keys = [], []
        for line in ddl.splitlines():
            if line.lstrip().startswith("CONSTRAINT "):
                foreign_key = line.strip().rstrip(",")
                match = re.fullmatch(r"CONSTRAINT `[A-Za-z0-9_]+` FOREIGN KEY " + column_list +
                                     r" REFERENCES `([a-z0-9_]+)` " + column_list +
                                     r"(?: ON (?:DELETE|UPDATE) (?:RESTRICT|CASCADE|SET NULL|NO ACTION))*", foreign_key)
                require(match and match[1] in table_names, "Unknown or cross-schema foreign key")
                foreign_keys.append(foreign_key)
            else:
                lines.append(line)
        require(lines[-1] == ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "Unexpected table options or appended SQL")
        lines[-2] = lines[-2].rstrip(",")
        lines[0] = lines[0].replace(quoted(name), qualified(name), 1)
        creates.append("\n".join(lines))
        constraints.extend("ALTER TABLE " + qualified(name) + " ADD " + fk for fk in foreign_keys)
    require(len(constraints) == 65, "Expected 65 foreign keys")
    return manifest, catalog, creates, constraints


def install(manifest, catalog, creates, constraints):
    secret = os.environ.pop("ESTUDAI_BOOTSTRAP_PASSWORD", None)
    require(secret and MYSQL.is_file(), "Protected administrative credential or MySQL client missing")

    def run(sql, database=None):
        args = [str(MYSQL), "--no-defaults", "--protocol=TCP", "--host=localhost", "--port=3306",
                "--user=root", "--connect-timeout=5", "--default-character-set=utf8mb4",
                "--batch", "--raw", "--skip-column-names"]
        if database:
            require(database == TARGET, "Unknown execution database")
            args.append("--database=" + TARGET)
        env = os.environ.copy()
        env["MYSQL_PWD"] = secret
        process = subprocess.run(args, input=sql.encode("utf-8"), capture_output=True, env=env)
        env.pop("MYSQL_PWD", None)
        if process.returncode:
            # Metadata/catalog operations contain no passwords; still avoid
            # echoing SQL or client diagnostics into the terminal.
            raise RuntimeError("MySQL failed during " + sql.split(None, 1)[0] +
                               "; preserve partial installation and investigate")
        return process.stdout.decode("utf-8").strip()

    identity = json.loads(run("SELECT JSON_OBJECT('version',VERSION(),'uuid',@@server_uuid,'port',@@port);"))
    require(identity == manifest["server"], "Server identity mismatch")
    exists = int(run("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='" + TARGET + "';"))
    require(exists == 0, "Target already exists; overwrite and replay refused")
    run("CREATE DATABASE " + quoted(TARGET) + " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
    require(run("SELECT DATABASE();", TARGET) == TARGET, "Selected database mismatch")
    for statement in creates + constraints:
        run(statement + ";", TARGET)
    inserts = ["START TRANSACTION"]
    for name in CATALOGS:
        for row in catalog[name]:
            columns = list(row)
            inserts.append("INSERT INTO " + qualified(name) + " (" + ",".join(map(quoted, columns)) +
                           ") VALUES (" + ",".join(literal(row[c]) for c in columns) + ")")
    inserts.append("COMMIT")
    run(";\n".join(inserts) + ";", TARGET)
    counts = {}
    for table in [x["name"] for x in json.loads((ROOT / "structure_stage06.json").read_text(encoding="utf-8"))]:
        counts[table] = int(run("SELECT COUNT(*) FROM " + qualified(table) + ";", TARGET))
        require(counts[table] == len(catalog.get(table, [])), "Unexpected data outside static catalogs")
    metrics = json.loads(run("""SELECT JSON_OBJECT(
        'tables',(SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()),
        'columns',(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()),
        'indexes',(SELECT COUNT(*) FROM (SELECT TABLE_NAME,INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() GROUP BY TABLE_NAME,INDEX_NAME) s),
        'foreign_keys',(SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()),
        'routines',(SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()));""", TARGET))
    require(metrics == dict(tables=42, columns=406, indexes=139, foreign_keys=65, routines=0), "Structural metric mismatch")
    return dict(target=TARGET, created=True, metrics=metrics, counts=counts, personal_tables_empty=True,
                foreign_key_checks_disabled=False, historical_procedure_copied=False)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--create", action="store_true", help="Create the fixed absent target; never overwrite")
    args = parser.parse_args()
    manifest, catalog, creates, constraints = validate_fixtures()
    if args.create:
        print(json.dumps(install(manifest, catalog, creates, constraints), ensure_ascii=True))
    else:
        print(json.dumps(dict(validated=True, target=TARGET, tables=len(creates), foreign_keys=len(constraints),
                              catalog_counts=manifest["catalog_counts"], database_operations=0)))


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, ValueError, OSError) as error:
        # SQL payloads and credentials are deliberately never printed.
        raise SystemExit(str(error))
