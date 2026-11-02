"""Association rules (Apriori) over loan baskets: which items are borrowed together."""
from __future__ import annotations
from itertools import combinations
import math
import pandas as pd
from .repository import COUNTED


def baskets(lines: pd.DataFrame) -> list[frozenset]:
    df = lines[lines["status"].isin(COUNTED)]
    return [frozenset(g["item_id"].astype(int)) for _, g in df.groupby("loan_id")]


def apriori(bs: list[frozenset], min_count: int, max_len: int = 3) -> dict[frozenset, int]:
    counts: dict[frozenset, int] = {}
    single: dict[int, int] = {}
    for b in bs:
        for i in b:
            single[i] = single.get(i, 0) + 1
    freq = {frozenset([i]): c for i, c in single.items() if c >= min_count}
    counts.update(freq)
    level = list(freq)
    k = 2
    while level and k <= max_len:
        items = sorted({i for s in level for i in s})
        cands = set()
        for s in level:
            for i in items:
                if i not in s:
                    c = s | {i}
                    if len(c) == k and all(frozenset(sub) in counts for sub in combinations(c, k - 1)):
                        cands.add(c)
        cnt = {c: 0 for c in cands}
        for b in bs:
            for c in cands:
                if c <= b:
                    cnt[c] += 1
        level = [c for c, v in cnt.items() if v >= min_count]
        counts.update({c: cnt[c] for c in level})
        k += 1
    return counts


def rules(bs: list[frozenset], min_count: int = 8, min_conf: float = 0.3, min_lift: float = 1.2, top: int = 15) -> list[dict]:
    n = len(bs)
    if n == 0:
        return []
    freq = apriori(bs, min_count)
    out = []
    for s, c in freq.items():
        if len(s) < 2:
            continue
        for cons in s:
            ante = s - {cons}
            conf = c / freq[ante]
            lift = conf / (freq[frozenset([cons])] / n)
            if conf >= min_conf and lift >= min_lift:
                out.append({"antecedent": sorted(int(x) for x in ante), "consequent": int(cons), "count": int(c),
                            "support": round(c / n, 4), "confidence": round(conf, 4), "lift": round(lift, 3)})
    out.sort(key=lambda r: r["lift"] * math.log(r["count"] + 1), reverse=True)
    return out[:top]
