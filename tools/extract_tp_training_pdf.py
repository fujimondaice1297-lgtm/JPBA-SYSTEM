#!/usr/bin/env python3
"""Extract standard male/female JPBA licence numbers from the official TP list PDF."""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path

import pdfplumber


PAGE_BANDS = {
    "M": [(40, 58), (131, 149), (222, 240), (313, 331), (404, 422), (495, 513)],
    "F": [(37, 57), (149, 168), (260, 279), (371, 391), (482, 502)],
}


def extract_page(page, gender: str) -> list[int]:
    words = page.extract_words(x_tolerance=1, y_tolerance=2)
    columns: list[list[int]] = []
    for left, right in PAGE_BANDS[gender]:
        values = [
            int(word["text"])
            for word in words
            if word["top"] > 75
            and word["text"].isdigit()
            and word["x0"] >= left
            and word["x1"] <= right
        ]
        columns.append(values)
    return [number for column in columns for number in column]


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("input_pdf", type=Path)
    parser.add_argument("output_json", type=Path)
    parser.add_argument("--source-url", required=True)
    parser.add_argument("--source-page-url", required=True)
    parser.add_argument("--published-at", required=True)
    parser.add_argument("--edition", required=True, type=int)
    parser.add_argument("--valid-from", required=True)
    parser.add_argument("--valid-through", required=True)
    args = parser.parse_args()

    with pdfplumber.open(args.input_pdf) as document:
        if len(document.pages) < 2:
            raise ValueError("The official list must contain male and female pages.")
        entries = {
            "M": extract_page(document.pages[0], "M"),
            "F": extract_page(document.pages[1], "F"),
        }

    for gender, numbers in entries.items():
        if not numbers or len(numbers) != len(set(numbers)):
            raise ValueError(f"Invalid or duplicate {gender} licence numbers.")

    payload = {
        "title": f"第{args.edition}回JPBAトーナメントプレイヤー講習会 受講修了者リスト",
        "edition_number": args.edition,
        "valid_from": args.valid_from,
        "valid_through": args.valid_through,
        "source_page_url": args.source_page_url,
        "source_url": args.source_url,
        "source_published_at": args.published_at,
        "source_sha256": hashlib.sha256(args.input_pdf.read_bytes()).hexdigest(),
        "date_precision": "official_cycle",
        "notes": "受講日は公開PDFにないため、公式の開催サイクルを資格有効期間の根拠として使用する。",
        "entries": entries,
    }
    args.output_json.parent.mkdir(parents=True, exist_ok=True)
    args.output_json.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"M={len(entries['M'])} F={len(entries['F'])} total={sum(map(len, entries.values()))}")
    print(args.output_json)


if __name__ == "__main__":
    main()
