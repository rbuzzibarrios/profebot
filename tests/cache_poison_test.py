#!/usr/bin/env python3
"""Integration test for cache poison defenses (parseQ's server-side mirror).

Exercises the real profebot.php dispatch over HTTP:
  - cache_save rejects malformed / leaked-reasoning questions
  - cache_get lazy-heals: drops poisoned entries on read and rewrites the key

Focuses on the single-objective scenario: one cache key, many reads — the hot
path when the user selects just one tema. Grade/subject agnostic.

Backs up question_cache.json and restores it on exit. Run from repo root:
    python tests/cache_poison_test.py
"""
import json
import os
import shutil
import subprocess
import sys
import time
import urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CACHE = os.path.join(ROOT, "question_cache.json")
BAK = CACHE + ".testbak"
ENV = os.path.join(ROOT, ".env")
ENV_BAK = ENV + ".testbak"
HOST, PORT = "127.0.0.1", 8099
BASE = f"http://{HOST}:{PORT}/profebot.php"

# Single selected tema -> single key (grade/subject irrelevant to the logic).
KEY = "test::solo::tema"
PKEY = "preesc::test::tema"  # preesc -> 2-option rule applies

def clean(n):
    return {"question": f"Pregunta limpia {n}?", "opts": {"A": "uno", "B": "dos"},
            "correct": "A", "explanation": "Porque uno."}

POISON_C = {"question": "cuantas R?", "opts": {"A": "4", "B": "5"},
            "correct": "C", "explanation": "leak"}            # correct not in opts
POISON_MARK = {"question": "PREGUNTA: basura", "opts": {"A": "a", "B": "b"},
               "correct": "A", "explanation": "x"}            # leaked format marker
PREESC_4OPT = {"question": "2+3?", "opts": {"A": "4", "B": "5", "C": "6", "D": "7"},
               "correct": "B", "explanation": "x"}            # 4 opts under preesc key

def post(action, **kw):
    body = json.dumps({"action": action, **kw}).encode()
    req = urllib.request.Request(BASE, data=body,
                                 headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=10) as r:
        return json.loads(r.read().decode())

def read_cache():
    with open(CACHE, encoding="utf-8") as f:
        return json.load(f)

def write_cache(d):
    with open(CACHE, "w", encoding="utf-8") as f:
        json.dump(d, f, ensure_ascii=False)

def wait_up(proc):
    for _ in range(50):
        if proc.poll() is not None:
            sys.exit("server died on startup")
        try:
            post("cache_get", cache_key="__ping__")
            return
        except Exception:
            time.sleep(0.2)
    sys.exit("server never came up")

results = []
def check(name, ok):
    results.append((name, ok))
    print(f"{'PASS' if ok else 'FAIL'}  {name}")

def main():
    shutil.copyfile(CACHE, BAK)
    # Stash .env so the server runs hermetic: no real provider keys and, crucially,
    # no PROFEBOT_SLACK_WEBHOOK_URL (.env is putenv'd every request, so reject-
    # warnings would POST to the real Slack channel and add flaky latency).
    env_stashed = os.path.exists(ENV)
    if env_stashed:
        shutil.move(ENV, ENV_BAK)
    # Seed: poison + 5 clean on KEY (>=5 so cache_get won't randomly skip),
    # and a 4-option entry + a clean one on the preesc key.
    seed = read_cache()
    seed[KEY] = [POISON_C, POISON_MARK] + [clean(i) for i in range(5)]
    seed[PKEY] = [PREESC_4OPT, {"question": "Que es?", "opts": {"A": "sol", "B": "luna"},
                                "correct": "B", "explanation": "y"}]
    write_cache(seed)

    proc = subprocess.Popen(["php", "-S", f"{HOST}:{PORT}", "router.php"],
                            cwd=ROOT,
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        wait_up(proc)

        # 1. cache_get lazy-heals KEY: 2 poison dropped, 5 clean kept, file rewritten.
        post("cache_get", cache_key=KEY, exclude=[])
        kept = read_cache().get(KEY, [])
        check("lazy-heal drops 2 poison, keeps 5 clean on read",
              len(kept) == 5 and all(q["correct"] in ("A", "B") for q in kept)
              and not any("PREGUNTA" in q["question"] for q in kept))

        # 2. preesc 4-option entry dropped on read, 2-option kept.
        post("cache_get", cache_key=PKEY, exclude=[])
        pk = read_cache().get(PKEY, [])
        check("lazy-heal drops preesc 4-option entry",
              len(pk) == 1 and not (pk[0]["opts"].get("C") or pk[0]["opts"].get("D")))

        # 3. cache_save rejects poison (correct not in opts).
        r = post("cache_save", cache_key=KEY, question=POISON_C)
        check("cache_save rejects correct-not-in-opts", r.get("saved") is False)

        # 4. cache_save rejects leaked marker.
        r = post("cache_save", cache_key=KEY, question=POISON_MARK)
        check("cache_save rejects leaked format marker", r.get("saved") is False)

        # 5. cache_save rejects preesc 4-option.
        r = post("cache_save", cache_key=PKEY, question=PREESC_4OPT)
        check("cache_save rejects preesc 4-option", r.get("saved") is False)

        # 6. cache_save accepts a clean question (and it lands in the file).
        before = len(read_cache().get(KEY, []))
        r = post("cache_save", cache_key=KEY, question=clean(99))
        after = len(read_cache().get(KEY, []))
        check("cache_save accepts clean question", r.get("saved") is True and after == before + 1)

    finally:
        proc.terminate()
        try:
            proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proc.kill()
        shutil.move(BAK, CACHE)  # restore original cache
        if env_stashed:
            shutil.move(ENV_BAK, ENV)  # restore .env

    failed = [n for n, ok in results if not ok]
    print(f"\n{len(results) - len(failed)}/{len(results)} passed")
    sys.exit(1 if failed else 0)

if __name__ == "__main__":
    main()
