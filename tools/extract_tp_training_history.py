#!/usr/bin/env python3
"""Build auditable historical JPBA TP-training datasets from official PDFs.

Only PDFs whose headings state that the people attended or completed the
training are imported.  The 2014 PDF is intentionally excluded because its
heading says it is an application list (受講申込者リスト), not proof of
attendance.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
from dataclasses import dataclass
from pathlib import Path

import pdfplumber


SOURCE_PAGE_URL = "https://www.jpba1.jp/mypage/notification/TPT.html"


@dataclass(frozen=True)
class DatasetSpec:
    filename: str
    output: str
    title: str
    edition_number: int
    valid_from: str
    valid_through: str
    source_url: str
    source_published_at: str
    date_precision: str
    notes: str


def leading_integer(text: str) -> int | None:
    match = re.match(r"[0-9]+", text)
    return int(match.group()) if match else None


def numbers_in_band(page, left: float, right: float, *, top_min: float = 0, top_max: float | None = None) -> list[int]:
    values: list[int] = []
    for word in page.extract_words():
        top = float(word["top"])
        if not (left <= float(word["x0"]) < right) or top <= top_min:
            continue
        if top_max is not None and top >= top_max:
            continue
        number = leading_integer(str(word["text"]))
        if number is not None:
            values.append(number)
    return values


def unique_in_source_order(values: list[int]) -> list[int]:
    return list(dict.fromkeys(values))


def extract_2015(pdf_path: Path) -> dict[str, list[int]]:
    page = pdfplumber.open(pdf_path).pages[0]
    male_bands = [(58, 85), (158, 190), (262, 300), (365, 410)]
    female_bands = [(505, 550), (608, 655)]
    return {
        "M": [number for band in male_bands for number in numbers_in_band(page, *band, top_min=100)],
        "F": [number for band in female_bands for number in numbers_in_band(page, *band, top_min=100)],
    }


def extract_2016(pdf_path: Path) -> dict[str, list[int]]:
    page = pdfplumber.open(pdf_path).pages[0]
    male = numbers_in_band(page, 125, 165, top_min=120)
    male += numbers_in_band(page, 380, 425, top_min=580, top_max=745)
    female = numbers_in_band(page, 385, 425, top_min=120, top_max=520)
    return {"M": male, "F": female}


def extract_2017(pdf_path: Path) -> dict[str, list[int]]:
    page = pdfplumber.open(pdf_path).pages[0]
    # T009 and T016 are non-standard teaching licences and are deliberately
    # not interpreted as M/F professional licences.
    return {
        "M": numbers_in_band(page, 108, 155, top_min=140),
        "F": numbers_in_band(page, 358, 410, top_min=140),
    }


def extract_2018(pdf_path: Path) -> dict[str, list[int]]:
    document = pdfplumber.open(pdf_path)
    page1, page2 = document.pages
    bands = [(64, 105), (160, 205), (286, 330), (382, 430), (508, 550), (604, 650)]

    male = []
    female = []
    for index, band in enumerate(bands):
        values = numbers_in_band(page1, *band, top_min=170)
        (male if index % 2 == 0 else female).extend(values)

    # On page 2 the 20 new male professionals of the 20th course continue
    # below the female table in the adjacent licence-number column.
    male.extend(numbers_in_band(page2, *bands[0], top_min=170))
    male.extend(numbers_in_band(page2, *bands[1], top_min=740))
    female.extend(numbers_in_band(page2, *bands[1], top_min=170, top_max=740))
    for index, band in enumerate(bands[2:], start=2):
        values = numbers_in_band(page2, *band, top_min=170)
        (male if index % 2 == 0 else female).extend(values)

    return {"M": unique_in_source_order(male), "F": unique_in_source_order(female)}


def extract_2019(_: Path) -> dict[str, list[int]]:
    # This PDF embeds the licence digits with a font encoding that does not
    # expose them to PDF text extractors.  Values were transcribed from the
    # rendered official table and checked against all 66 male / 16 female rows.
    male = [
        331, 390, 432, 495, 698, 738, 741, 774, 787, 830, 850, 858, 870, 937,
        962, 995, 1003, 1011, 1019, 1071, 1088, 1095, 1109, 1117, 1131, 1161,
        1169, 1232, 1240, 1245, 1262, 1283, 1289, 1292, 1294, 1299, 1300,
        1301, 1303, 1304, 1308, 1309, 1314, 1322, 1323, 1326, 1347, 1366,
        1368, 1369, 1370, 1371, 1372, 1373, 1374, 1375, 1377,
        1414, 1415, 1416, 1417, 1418, 1419, 1420, 1421, 1422,
    ]
    female = [495, 498, 500, 542, 543, 544, 545, 546, 549, 550, 553, 555, 578, 579, 580, 581]
    return {"M": male, "F": female}


def extract_2024(pdf_path: Path) -> dict[str, list[int]]:
    document = pdfplumber.open(pdf_path)
    male_bands = [(65, 100), (165, 200), (262, 300), (363, 400), (463, 505)]
    female_bands = [(75, 110), (198, 235), (322, 360), (446, 485)]
    return {
        "M": [number for band in male_bands for number in numbers_in_band(document.pages[0], *band, top_min=75)],
        "F": [number for band in female_bands for number in numbers_in_band(document.pages[1], *band, top_min=75)],
    }


EXTRACTORS = {
    "TPclass_2015.pdf": extract_2015,
    "TPclass_2016.pdf": extract_2016,
    "TPclass_2017.pdf": extract_2017,
    "TPclass_2018.pdf": extract_2018,
    "2019_23rd_0520.pdf": extract_2019,
    "2024_24th_0528.pdf": extract_2024,
}


EXPECTED_COUNTS = {
    "TPclass_2015.pdf": (365, 194),
    "TPclass_2016.pdf": (65, 30),
    "TPclass_2017.pdf": (41, 30),
    "TPclass_2018.pdf": (347, 203),
    "2019_23rd_0520.pdf": (66, 16),
    "2024_24th_0528.pdf": (396, 253),
}


SPECS = [
    DatasetSpec(
        "TPclass_2015.pdf",
        "jpba_official_tp_training_history_2015.json",
        "第9回～第14回JPBAトーナメントプレイヤー講習会 受講者リスト",
        14,
        "2015-01-01",
        "2018-12-31",
        "https://www.jpba1.jp/mypage/notification/TPTraining/TPclass_2015.pdf",
        "2015-12-31T23:59:59+09:00",
        "year",
        "第9回～第14回の受講者をまとめた公式資料。個別受講日は資料にないため年度単位で保存。",
    ),
    DatasetSpec(
        "TPclass_2016.pdf",
        "jpba_official_tp_training_history_2016.json",
        "第15回JPBAトーナメントプレイヤー講習会 受講者リスト",
        15,
        "2016-05-12",
        "2019-05-11",
        "https://www.jpba1.jp/mypage/notification/TPTraining/TPclass_2016.pdf",
        "2016-05-12T17:30:00+09:00",
        "exact_day",
        "公式資料に終了済みと記載。ティーチングプロ受講者4名はM/F標準ライセンス照合から除外。",
    ),
    DatasetSpec(
        "TPclass_2017.pdf",
        "jpba_official_tp_training_history_2017.json",
        "第16回JPBAトーナメントプレイヤー講習会 受講者リスト",
        16,
        "2017-05-20",
        "2020-05-19",
        "https://www.jpba1.jp/mypage/notification/TPTraining/TPclass_2017.pdf",
        "2017-05-25T21:03:13+09:00",
        "exact_day",
        "公式資料に終了済みと記載。T009・T016はM/F標準ライセンス照合から除外。",
    ),
    DatasetSpec(
        "TPclass_2018.pdf",
        "jpba_official_tp_training_history_2018.json",
        "第17回～第22回JPBAトーナメントプレイヤー講習会 参加者リスト",
        22,
        "2018-01-22",
        "2021-07-03",
        "https://www.jpba1.jp/mypage/notification/TPTraining/TPclass_2018.pdf",
        "2018-07-06T16:39:29+09:00",
        "course_range",
        "第17回～第22回の参加者をまとめた公式資料。重複番号は出典順で一件に統合。",
    ),
    DatasetSpec(
        "2019_23rd_0520.pdf",
        "jpba_official_tp_training_history_2019.json",
        "第23回JPBAトーナメントプレイヤー講習会 受講者リスト",
        23,
        "2019-05-18",
        "2022-05-17",
        "https://www.jpba1.jp/mypage/notification/TPTraining/2019_23rd/2019_23rd_0520.pdf",
        "2019-05-20T12:40:53+09:00",
        "exact_day",
        "公式資料に終了済みと記載。PDF描画表を66名・16名の全行で照合。",
    ),
    DatasetSpec(
        "2024_24th_0528.pdf",
        "jpba_official_tp_training_history_2024.json",
        "第24回JPBAトーナメントプレイヤー講習会（オンライン）受講修了者リスト",
        24,
        "2021-09-01",
        "2024-12-31",
        "https://www.jpba1.jp/mypage/notification/TPTraining/2021_2022_24th/2024_24th_0528.pdf",
        "2024-05-28T14:39:37+09:00",
        "official_cycle",
        "第25回案内により、2025年度以降の公式トーナメント出場には第25回受講が必要。",
    ),
]


def build_dataset(spec: DatasetSpec, source_dir: Path) -> dict:
    pdf_path = source_dir / spec.filename
    if not pdf_path.is_file():
        raise FileNotFoundError(pdf_path)

    entries = EXTRACTORS[spec.filename](pdf_path)
    for gender in ("M", "F"):
        entries[gender] = unique_in_source_order(entries[gender])
        if len(entries[gender]) != len(set(entries[gender])):
            raise ValueError(f"duplicate {gender} licence in {spec.filename}")

    expected_male, expected_female = EXPECTED_COUNTS[spec.filename]
    actual = (len(entries["M"]), len(entries["F"]))
    if actual != (expected_male, expected_female):
        raise ValueError(f"unexpected count for {spec.filename}: {actual}")

    return {
        "title": spec.title,
        "edition_number": spec.edition_number,
        "valid_from": spec.valid_from,
        "valid_through": spec.valid_through,
        "source_page_url": SOURCE_PAGE_URL,
        "source_url": spec.source_url,
        "source_published_at": spec.source_published_at,
        "source_sha256": hashlib.sha256(pdf_path.read_bytes()).hexdigest(),
        "date_precision": spec.date_precision,
        "is_current": False,
        "allow_unmatched": True,
        "notes": spec.notes,
        "entries": entries,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-dir", type=Path, default=Path("tmp/pdfs/tp_training_history"))
    parser.add_argument("--output-dir", type=Path, default=Path("database/data"))
    args = parser.parse_args()

    args.output_dir.mkdir(parents=True, exist_ok=True)
    for spec in SPECS:
        payload = build_dataset(spec, args.source_dir)
        destination = args.output_dir / spec.output
        destination.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        total = len(payload["entries"]["M"]) + len(payload["entries"]["F"])
        print(f"{destination}: M={len(payload['entries']['M'])} F={len(payload['entries']['F'])} total={total}")

    print("TPclass_2014.pdf: excluded (application list only; not attendance evidence)")


if __name__ == "__main__":
    main()
