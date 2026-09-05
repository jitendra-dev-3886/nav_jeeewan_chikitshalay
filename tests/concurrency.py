"""Parallel booking requests against a disposable SQLite file, never the clinic DB."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import subprocess
import sys
root=Path(__file__).resolve().parent.parent
path=root/'.local/concurrency.sqlite'
path.parent.mkdir(exist_ok=True)
mysql='--mysql' in sys.argv
if not mysql and path.exists():
    raise SystemExit('The isolated concurrency database already exists. Use a fresh test file before rerunning.')
if not mysql:path.touch()
worker=root/'clinic-backend/scripts/concurrency-worker.php'
def run(phone):
    result=subprocess.run(['php',str(worker),'mysql-test' if mysql else str(path),phone],capture_output=True,text=True,check=True)
    return result.stdout.strip()
assert run('setup')=='ready'
with ThreadPoolExecutor(max_workers=8) as pool:
    results=list(pool.map(run,[f'98765000{i:02d}' for i in range(8)]))
assert results.count('booked')==1,results
assert results.count('conflict')==7,results
assert run('count')=='1'
print('PASS: 8 concurrent requests, 1 booking, 7 slot conflicts, capacity preserved.')
