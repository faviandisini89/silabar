"""Synthetic SILABAR database (18 months of loans) used by the test-suite and for demos."""
from __future__ import annotations
import math
import sqlite3
from pathlib import Path
import numpy as np
import pandas as pd

SCHEMA = (Path(__file__).resolve().parents[1] / "silabar_analytics" / "schema.sql").read_text()
F = "%Y-%m-%d %H:%M:%S"
H, D = pd.Timedelta(hours=1), pd.Timedelta(days=1)
# id, sku, name, tracking, count, max_days, approval, value
ITEMS = [(1, "PRJ", "Proyektor", "unit", 6, 3, 1, 5800000), (2, "LTP", "Laptop", "unit", 5, 3, 1, 9500000), (3, "CAM", "Kamera", "unit", 3, 3, 1, 12500000),
         (4, "TRP", "Tripod", "unit", 4, 3, 0, 900000), (5, "MIC", "Mikrofon", "unit", 4, 3, 1, 3200000), (6, "SPK", "Speaker", "unit", 3, 2, 1, 4800000),
         (7, "SCR", "Layar", "unit", 3, 3, 0, 1100000), (8, "HDM", "Kabel HDMI", "bulk", 12, 5, 0, 85000), (9, "TRM", "Terminal", "bulk", 20, 5, 0, 120000),
         (10, "KRS", "Kursi lipat", "bulk", 80, 3, 0, 150000), (11, "MJA", "Meja lipat", "bulk", 30, 3, 0, 350000), (12, "TND", "Tenda", "unit", 4, 3, 1, 2200000),
         (13, "WBD", "Whiteboard", "unit", 4, 5, 0, 750000), (14, "WKT", "Walkie talkie", "unit", 8, 3, 0, 600000)]
BUNDLES = [(3, [1, 8, 9]), (2, [3, 4]), (2.5, [5, 6]), (1.6, [10, 11, 12]), (1.5, [2, 9]), (1, [1, 7]), (1.4, [13]), (1.4, [14]), (2.6, [1]), (1.4, [2]), (1.8, [10]), (.8, [12]), (.8, [3]), (.6, [6]), (.6, [5])]
LATE = [.03, .55, .08, .20, .05, .45, .06, .30, .10, .25, .04, .40]
DOW = [.15, 1.2, 1.35, 1.4, 1.2, .9, .4]
MON = [.6, 1.1, 1.3, 1.2, 1.2, .7, .5, .6, 1.2, 1.45, 1.3, .8]
HW = {7: 1, 8: 4, 9: 5, 10: 4, 11: 2, 12: 1, 13: 3, 14: 3, 15: 2, 16: 1}


def s(t):
    return None if t is None else pd.Timestamp(t).strftime(F)


def build(path: str, seed: int = 11, now=None, months: float = 18) -> pd.Timestamp:
    rng = np.random.default_rng(seed)
    now = pd.Timestamp(now or pd.Timestamp.now()).floor("min")
    today = now.normalize()
    Path(path).unlink(missing_ok=True)
    con = sqlite3.connect(path)
    con.executescript(SCHEMA)
    cur = con.cursor()
    cur.execute("INSERT INTO users (id,name,email,role,trust_score,is_active) VALUES (1,'Admin','a@x','admin',100,1),(2,'Petugas','p@x','petugas',100,1)")
    for i in range(12):
        cur.execute("INSERT INTO users (id,name,email,role,trust_score,is_active) VALUES (?,?,?,?,?,1)", (3 + i, f"Peminjam {i+1}", f"b{i}@x", "peminjam", 70))
    cur.execute("INSERT INTO categories VALUES (1,'Umum','umum')")
    cur.execute("INSERT INTO locations VALUES (1,'Gudang','')")
    units = {}
    uid = 1
    for iid, sku, name, tr, cnt, md, ap, val in ITEMS:
        cur.execute("INSERT INTO items (id,category_id,location_id,name,sku,tracking_type,total_stock,max_loan_days,requires_approval,replacement_value,tile) VALUES (?,?,?,?,?,?,?,?,?,?,0)",
                    (iid, 1, 1, name, sku, tr, cnt if tr == "bulk" else 0, md, ap, val))
        if tr == "unit":
            units[iid] = []
            for n in range(1, cnt + 1):
                cur.execute("INSERT INTO item_units (id,item_id,asset_code,condition,status) VALUES (?,?,?,?,'available')", (uid, iid, f"{sku}-{n:03d}", "good"))
                units[iid].append(uid)
                uid += 1
    total = {i[0]: i[4] for i in ITEMS}
    maxd = {i[0]: i[5] for i in ITEMS}
    approval = {i[0]: i[6] for i in ITEMS}

    def usable(iid, at):
        if iid == 3:
            return 3 - (1 if at >= now - 38 * D else 0) - (1 if at >= now - 21 * D else 0)
        return total[iid]

    res = {i[0]: [] for i in ITEMS}  # (start, end, qty)

    def reserved(iid, a, b):
        ev = []
        for st, en, q in res[iid]:
            if en <= a or st >= b:
                continue
            ev += [(max(st, a), q), (min(en, b), -q)]
        ev.sort(key=lambda x: (x[0], x[1]))
        cur_, mx = 0, 0
        for _, q in ev:
            cur_ += q
            mx = max(mx, cur_)
        return mx

    reqs = []
    for d in range(-int(months * 29), 13):
        day = today + d * D
        lam = 1.25 * DOW[day.weekday() % 7 if False else (day.weekday() + 1) % 7] * MON[day.month - 1] * (0.75 + 0.5 * (d + 520) / 532)
        for _ in range(rng.poisson(lam)):
            b = BUNDLES[rng.choice(len(BUNDLES), p=np.array([x[0] for x in BUNDLES]) / sum(x[0] for x in BUNDLES))][1]
            who = int(rng.integers(0, 12))
            hr = int(rng.choice(list(HW), p=np.array(list(HW.values())) / sum(HW.values())))
            start = day + hr * H + int(rng.integers(0, 4)) * pd.Timedelta(minutes=15)
            md = min(maxd[i] for i in b)
            days = min(int(rng.choice([1, 2, 3], p=[.5, .3, .2])), md)
            created = start - int(rng.integers(8, 6 * 24)) * H
            if created > now:
                continue
            qty = {8: int(rng.integers(1, 4)), 9: int(rng.integers(1, 4)), 10: int(rng.integers(10, 56)), 11: int(rng.integers(4, 15)), 12: int(rng.integers(1, 3)), 14: int(rng.integers(1, 4))}
            reqs.append((created, start, start + days * D, who, [(i, qty.get(i, 1)) for i in b]))
    reqs.sort(key=lambda r: r[0])
    lid, did, uc = 0, 0, 0
    unmet_rows = []
    loan_rows = []

    def add_loan(created, start, due, who, lines, status, handover=None, returned=None, late=0):
        nonlocal lid, did
        lid += 1
        cur.execute("INSERT INTO loans (id,loan_code,borrower_id,approved_by,handed_over_by,status,purpose,start_at,due_at,approved_at,handed_over_at,returned_at,late_days,qr_token,created_at,updated_at) "
                    "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    (lid, f"SLB-{lid:05d}", 3 + who, 2, 2 if handover is not None else None, status, "Kegiatan", s(start), s(due), s(created + 2 * H),
                     s(handover), s(returned), late, f"TOK{lid}", s(created), s(created)))
        for iid, q in lines:
            did += 1
            cur.execute("INSERT INTO loan_details (id,loan_id,item_id,qty) VALUES (?,?,?,?)", (did, lid, iid, q))
        return lid

    for created, start, due, who, lines in reqs:
        fail = False
        for iid, q in lines:
            u = usable(iid, created)
            r = reserved(iid, start, due)
            avail = max(0, u - r)
            if q > avail:
                fail = True
                reason = "date_conflict" if avail <= 0 and u > 0 else "out_of_stock"
                unmet_rows.append((iid, 3 + who, q, avail, s(start), s(due), reason, min(1.0, r / u) if u else 1.0, ((created >= now - 38 * D) + (created >= now - 21 * D)) / 3 if iid == 3 else 0.0, 0, s(created + 5 * pd.Timedelta(minutes=1))))
        if fail:
            continue
        status, hand, ret, late = "approved", None, None, 0
        x = rng.random()
        if start > now:
            status = "approved" if (created < now - 20 * H or not any(approval[i] for i, _ in lines)) else "pending"
        elif x < .035:
            status = "cancelled"
        elif x < .065:
            status = "rejected" if any(approval[i] for i, _ in lines) else "cancelled"
        else:
            hand = start + int(rng.integers(3, 40)) * pd.Timedelta(minutes=1)
            days = math.ceil((due - start) / D)
            wk = 1 if due.weekday() >= 5 else 0
            p_late = float(np.clip(LATE[who] + 0.14 * (days - 1) + 0.18 * wk + 0.10 * (len(lines) > 2), 0, .9))
            is_late = rng.random() < p_late
            ret = due + int(rng.integers(1, 4)) * D + int(rng.integers(0, 8)) * H if is_late else due - pd.Timedelta(seconds=int(rng.random() * .3 * (due - start).total_seconds()))
            ret = max(ret, hand + H)
            if ret <= now:
                status, late = "returned", (math.ceil((ret - due) / D) if ret > due else 0)
            else:
                status = "overdue" if due < now else "borrowed"
                ret = None
        if status in ("approved", "pending", "borrowed", "overdue", "returned"):
            for iid, q in lines:
                res[iid].append((start, (ret if status == "returned" else due), q))
        add_loan(created, start, due, who, lines, status, hand, ret, late)

    # --- crafted live scenario -------------------------------------------------
    def crafted(who, lines, off, days, status):
        start = today + off * D + 9 * H
        due = start + days * D
        for iid, q in lines:
            res[iid].append((start, due, q))
        return add_loan(min(now - 2 * H, start - 3 * H), start, due, who, lines, status, handover=(min(start + 20 * pd.Timedelta(minutes=1), now - H) if status in ("borrowed", "overdue") else None))

    crafted(0, [(1, 1), (8, 1), (9, 1)], -1, 2, "borrowed")
    crafted(5, [(5, 2), (6, 1)], -5, 3, "overdue")
    crafted(7, [(5, 1)], -4, 2, "overdue")
    crafted(1, [(1, 1)], 3, 1, "pending")
    # a demand spike for speakers last week
    wk = today - pd.Timedelta(days=today.weekday()) - pd.Timedelta(days=7)
    for k in range(9):
        st = wk + pd.Timedelta(days=int(k % 5)) + (9 + k % 4) * H
        add_loan(st - 2 * D, st, st + D, int(rng.integers(0, 12)), [(6, 1)], "returned", st + 10 * pd.Timedelta(minutes=1), st + 20 * H, 0)

    def um(iid, who, days_ago, hour, qty, avail, reason, util, maint, od, req_off=2):
        at = today - days_ago * D + hour * H
        st = at + req_off * D
        unmet_rows.append((iid, 3 + who, qty, avail, s(st), s(st + 2 * D), reason, util, maint, od, s(at)))

    for i, d in enumerate([1, 3, 4, 6, 8, 11, 13, 16, 19, 22, 25]):
        um(1, (i * 3 + 1) % 12, d, 9 + i % 5, 1 + i % 2, 0, "date_conflict" if i % 3 else "out_of_stock", .94 + rng.random() * .06, 0, 1 if i % 6 == 0 else 0)
    for i, d in enumerate([2, 5, 7, 10, 14, 18]):
        um(3, (i * 2 + 2) % 12, d, 10 + i % 4, 1, 0, "date_conflict", .75 + rng.random() * .25, .67, 0)
    for i, d in enumerate([1, 2, 4, 6, 9, 12, 15]):
        um(5, (i * 5 + 3) % 12, d, 9 + i % 5, 2, 1, "out_of_stock", .8 + rng.random() * .2, 0, 1 + i % 2)
    for i, d in enumerate([3, 4, 10, 11, 17, 18, 24, 25]):
        at_day = today - d * D
        req_off = ((7 - at_day.weekday()) % 7) or 7  # next Monday
        um(10, (i * 7 + 2) % 12, d, 8, 35 + i * 3, max(0, 25 - i * 2), "out_of_stock", .5 + rng.random() * .12, 0, 0, req_off)

    cur.executemany("INSERT INTO unmet_demand_logs (item_id,user_id,requested_qty,available_qty,requested_start_at,requested_end_at,reason,util,maint_share,overdue_units,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)", unmet_rows)
    # camera units under maintenance + a projector unit that keeps failing
    cam = units[3]
    cur.execute("UPDATE item_units SET status='maintenance', condition='damaged' WHERE id IN (?,?)", (cam[1], cam[2]))
    prj = units[1][3]
    for u, st, fin, status in [(cam[1], now - 38 * D, None, "open"), (cam[2], now - 21 * D, None, "open"), (prj, now - 84 * D, now - 80 * D, "done"), (prj, now - 52 * D, now - 49 * D, "done"), (prj, now - 23 * D, now - 19 * D, "done")]:
        cur.execute("INSERT INTO maintenance_logs (item_unit_id,reported_by,issue,status,cost,started_at,finished_at) VALUES (?,2,'Rusak',?,0,?,?)", (u, status, s(st), s(fin)))
    con.commit()
    con.close()
    return now
