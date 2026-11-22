#!/usr/bin/env bash
# Run ONLY on a disposable MariaDB database.
set -euo pipefail
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-vostera_ci}"
DB_NAME="${DB_NAME:-vostera_ci}"
: "${DB_PASSWORD:?Set the disposable test DB_PASSWORD}"
export MYSQL_PWD="$DB_PASSWORD"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
sql() { mariadb --batch --skip-column-names --raw --protocol=TCP --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" "$DB_NAME" "$@"; }
value() { printf '%s\n' "$1" | sql; }
must_equal() {
    local actual="$1" expected="$2" label="$3"
    if [[ "$actual" != "$expected" ]]; then
        echo "FAIL: $label: expected $expected, got $actual" >&2
        exit 1
    fi
    echo "OK: $label"
}
award() {
    local account="$1" token="$2" job="$3"
    value "CALL vostera_award_job($account, '$token', '$job', @res); SELECT @res;"
}
sql < "$ROOT/Мод/sql/001_accounts.sql"
sql < "$ROOT/Мод/sql/002_awards.sql"
sql < "$ROOT/Мод/sql/001_accounts.sql"
sql < "$ROOT/Мод/sql/002_awards.sql"

value "INSERT INTO vostera_accounts(username, password_hash)
    VALUES('Dev_Test_Alpha','TEST_ONLY_NOT_VALID_PASSWORD_HASH'),
          ('Dev_Test_Beta','TEST_ONLY_NOT_VALID_PASSWORD_HASH');
    INSERT INTO vostera_progress(account_id) SELECT id FROM vostera_accounts;"
a="$(value "SELECT id FROM vostera_accounts WHERE username='Dev_Test_Alpha'")"
b="$(value "SELECT id FROM vostera_accounts WHERE username='Dev_Test_Beta'")"
one="00000000-0000-4000-8000-000000000001"
two="00000000-0000-4000-8000-000000000002"
three="00000000-0000-4000-8000-000000000003"
four="00000000-0000-4000-8000-000000000004"

must_equal "$(award "$a" "$one" courier)" "awarded" "First courier payment"
must_equal "$(award "$a" "$one" courier)" "duplicate" "Duplicate event rejected"
must_equal "$(award "$a" "$two" mechanic)" "cooldown" "90-second cooldown"
must_equal "$(value "SELECT CONCAT(wallet,':',completed_jobs,':',row_version) FROM vostera_progress WHERE account_id=$a")" "125:1:1" "No duplicate money"
must_equal "$(award "$b" "$one" courier)" "duplicate" "Global event reuse rejected"
must_equal "$(award "$b" "$three" mechanic)" "awarded" "Independent account"
must_equal "$(value "SELECT wallet FROM vostera_progress WHERE account_id=$b")" "100" "Other wallet isolated"
value "UPDATE vostera_progress SET last_paid_job_at=UTC_TIMESTAMP(6)-INTERVAL 91 SECOND WHERE account_id=$a"
must_equal "$(award "$a" "$two" mechanic)" "awarded" "Work after cooldown"
must_equal "$(value "SELECT CONCAT(wallet,':',completed_jobs) FROM vostera_progress WHERE account_id=$a")" "225:2" "Progress persisted"
must_equal "$(award "$a" "$four" fishing)" "invalid" "Unknown job rejected"
must_equal "$(award "$a" not-a-uuid courier)" "invalid" "Malformed UUID rejected"
value "UPDATE vostera_accounts SET status='banned' WHERE id=$a"
must_equal "$(award "$a" "$four" courier)" "forbidden" "Banned account cannot earn"
value "UPDATE vostera_accounts SET status='active' WHERE id=$a"
value "UPDATE vostera_progress SET wallet=1999999990, last_paid_job_at=UTC_TIMESTAMP(6)-INTERVAL 91 SECOND WHERE account_id=$a"
must_equal "$(award "$a" "$four" courier)" "wallet_limit" "Wallet cap enforced"
must_equal "$(value "SELECT COUNT(*) FROM vostera_job_receipts")" "3" "Ledger contains only completed awards"
# Concurrent requests for the SAME completed event must not pay twice.
value "UPDATE vostera_progress SET last_paid_job_at=UTC_TIMESTAMP(6)-INTERVAL 91 SECOND WHERE account_id=$b"
five="00000000-0000-4000-8000-000000000005"
temp="$(mktemp -d)"
trap 'rm -rf "$temp"' EXIT
award "$b" "$five" courier > "$temp/one" &
pid1=$!
award "$b" "$five" courier > "$temp/two" &
pid2=$!
wait "$pid1"
wait "$pid2"
statuses="$(cat "$temp/one" "$temp/two" | sort | tr '\n' ',' | sed 's/,$//')"
must_equal "$statuses" "awarded,duplicate" "Concurrent duplicate event serialized"
must_equal "$(value "SELECT CONCAT(wallet,':',completed_jobs) FROM vostera_progress WHERE account_id=$b")" "225:2" "Concurrent calls paid exactly once"
must_equal "$(value "SELECT COUNT(*) FROM vostera_job_receipts")" "4" "Ledger reflects single concurrent payment"
if value "INSERT INTO vostera_accounts(username,password_hash) VALUES('dev_test_alpha','TEST_ONLY_NOT_VALID')" >/dev/null 2>&1; then
    echo "FAIL: username uniqueness/collation not enforced" >&2
    exit 1
fi
echo "OK: case-insensitive unique username"
if value "INSERT INTO vostera_progress(account_id) VALUES (99999999)" >/dev/null 2>&1; then
    echo "FAIL: foreign key not enforced" >&2
    exit 1
fi
echo "OK: progress foreign key"
value "INSERT INTO vostera_accounts(username,password_hash) VALUES('Dev_Test_Gamma','TEST_ONLY_NOT_VALID')"
gamma="$(value "SELECT id FROM vostera_accounts WHERE username='Dev_Test_Gamma'")"
must_equal "$(award "$gamma" "00000000-0000-4000-8000-000000000006" courier)" "no_progress" "Missing progress is not silently created"
echo "OK: MariaDB migrations, cooldown, idempotency, concurrent calls, bans, limits"
