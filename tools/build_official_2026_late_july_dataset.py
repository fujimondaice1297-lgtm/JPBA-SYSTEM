#!/usr/bin/env python3
"""Build the audited late-July 2026 JPBA result datasets from official PDFs.

The script is intentionally deterministic. It reads the already-downloaded
official PDFs, validates every table total, and only rewrites the four JSON
fixtures when --write is supplied.
"""

from __future__ import annotations

import argparse
import hashlib
import itertools
import json
import re
import unicodedata
from pathlib import Path
from typing import Any, Iterable

import pdfplumber


OOKA_KEY = "ooka_2026"
OOKA_TOURNAMENT = {
    "name": "大岡産業レディース［THE OPEN］トーナメント2026",
    "gender": "F",
    "start_date": "2026-07-22",
    "end_date": "2026-07-24",
    "year": 2026,
    "venue_name": "アルゴボウル",
    "competition_type": "singles",
    "official_type": "official",
    "counts_for_official_points": True,
    "counts_for_average": True,
    "counts_for_prize": True,
    "title_scope": "official",
    "title_category": "official",
    "source_url": "https://www.jpba.or.jp/information/tournament/tournament2026/10_TheOpen/TheOpen_2026.html",
}

SUMMER_D_KEY = "stsu_d"
SUMMER_D_NAME = "メリーランドカップ JPBAシーズントライアル2026 サマーシリーズ D会場"
SUMMER_D_URL = "https://www.jpba.or.jp/information/tournament/tournament2026/09_STSummer/Result/D_FinalResult.pdf"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--aggregate", type=Path, required=True)
    parser.add_argument("--season-detail", type=Path, required=True)
    parser.add_argument("--standard-detail", type=Path, required=True)
    parser.add_argument("--standard-final", type=Path, required=True)
    parser.add_argument("--summer-d-final", type=Path, required=True)
    parser.add_argument("--ooka-prelim", type=Path, required=True)
    parser.add_argument("--ooka-semifinal", type=Path, required=True)
    parser.add_argument("--ooka-round-robin", type=Path, required=True)
    parser.add_argument("--ooka-final", type=Path, required=True)
    parser.add_argument("--male-ranking", type=Path, required=True)
    parser.add_argument("--female-ranking", type=Path, required=True)
    parser.add_argument("--write", action="store_true")
    return parser.parse_args()


def read_json(path: Path) -> dict[str, Any]:
    return json.loads(path.read_text(encoding="utf-8"))


def write_json(path: Path, payload: dict[str, Any]) -> None:
    path.write_text(
        json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def as_int(value: Any, default: int = 0) -> int:
    if value is None:
        return default
    text = unicodedata.normalize("NFKC", str(value)).strip().replace(",", "").replace("\\", "")
    match = re.search(r"-?\d+", text)
    return int(match.group()) if match else default


def as_float(value: Any, default: float = 0.0) -> float:
    if value is None or str(value).strip() == "":
        return default
    return float(str(value).replace(",", "").strip())


def pro_license(prefix: str, value: Any) -> str:
    match = re.search(r"\d+", str(value or ""))
    if match is None:
        raise ValueError(f"Professional license number is missing: {value!r}")
    return prefix + match.group().zfill(8)


def person(prefix: str, raw_license: Any, display_name: str, event_key: str) -> dict[str, Any]:
    display_name = str(display_name).strip()
    if "アマ" in str(raw_license):
        digest = hashlib.sha256(
            f"{event_key}|{prefix}|{display_name}".encode("utf-8")
        ).hexdigest()[:12]
        return {
            "identity": f"A:{prefix}:{display_name}",
            "license_no": f"AMATEUR-{digest}",
            "display_name": display_name,
            "is_amateur": True,
        }
    license_no = pro_license(prefix, raw_license)
    return {
        "identity": f"P:{license_no}",
        "license_no": license_no,
        "display_name": display_name,
        "is_amateur": False,
    }


def aggregate_row(
    source: dict[str, Any],
    ranking: int,
    total_pin: int,
    games: int,
    points: int,
    prize_money: int,
    source_pdf: str,
) -> dict[str, Any]:
    return {
        **source,
        "ranking": ranking,
        "total_pin": total_pin,
        "games": games,
        "average": round(total_pin / games, 2) if games else 0.0,
        "points": points,
        "prize_money": prize_money,
        "source_pdf": source_pdf,
    }


def replace_event(events: list[dict[str, Any]], event: dict[str, Any]) -> None:
    events[:] = [row for row in events if row.get("key") != event["key"]]
    events.append(event)


def parse_rankings(path: Path, gender: str) -> list[dict[str, Any]]:
    prefix = "M" if gender == "M" else "F"
    rows: list[dict[str, Any]] = []
    with pdfplumber.open(path) as document:
        for page in document.pages:
            tables = page.extract_tables()
            if len(tables) != 1:
                raise ValueError(f"Ranking PDF page must contain one table: {path}")
            for cells in tables[0][1:]:
                rows.append(
                    {
                        "ranking_rank": as_int(cells[0]),
                        "license_no": pro_license(prefix, cells[1]),
                        "name_kanji": str(cells[2]).strip(),
                        "kibetsu": as_int(cells[3]),
                        "organization_name": str(cells[4] or "").strip(),
                        "tournaments": as_int(cells[5]),
                        "games": as_int(cells[6]),
                        "total_pin": as_int(cells[7]),
                        "average": as_float(cells[8]),
                        "points": as_int(cells[9]),
                        "prize_money": as_int(cells[10]),
                    }
                )
    expected = list(range(1, len(rows) + 1))
    actual = [row["ranking_rank"] for row in rows]
    if actual != expected:
        raise ValueError(f"Ranking sequence is invalid: {gender}")
    if len({row["license_no"] for row in rows}) != len(rows):
        raise ValueError(f"Ranking licenses are duplicated: {gender}")
    return rows


def split_series_scores(value: Any, expected_total: int) -> list[int | None]:
    text = str(value or "").strip().upper()
    if text == "" or "BL" in text:
        if expected_total != 0:
            raise ValueError(f"Blind series has a non-zero total: {value!r} / {expected_total}")
        return [None, None, None]

    digits = "".join(re.findall(r"\d", text))
    candidates: list[tuple[tuple[int, int, int], tuple[int, int, int]]] = []
    for widths in itertools.product((1, 2, 3), repeat=3):
        if sum(widths) != len(digits):
            continue
        offset = 0
        values = []
        for width in widths:
            values.append(int(digits[offset : offset + width]))
            offset += width
        if all(0 <= score <= 300 for score in values) and sum(values) == expected_total:
            candidates.append((widths, tuple(values)))
    if not candidates:
        raise ValueError(f"Cannot split three-game series: {value!r} / {expected_total}")
    candidates.sort(
        key=lambda candidate: (
            sum(score < 100 for score in candidate[1]),
            sum(abs(width - 3) for width in candidate[0]),
        )
    )
    best = candidates[0][1]
    if len({candidate[1] for candidate in candidates if candidate[0] == candidates[0][0]}) > 1:
        raise ValueError(f"Ambiguous three-game series: {value!r} / {expected_total}")
    return list(best)


def detail_row(
    source: dict[str, Any],
    games: Iterable[int | None],
    source_alias: str,
) -> dict[str, Any]:
    actual_games = [score for score in games if score is not None]
    return {
        **source,
        "games": [
            {"game_number": number, "score": int(score)}
            for number, score in enumerate(actual_games, start=1)
        ],
        "source_aliases": [source_alias],
        "stage_total_pin": sum(actual_games),
        "games_count": len(actual_games),
    }


def parse_ooka_prelim(path: Path) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    aggregate: list[dict[str, Any]] = []
    detail: list[dict[str, Any]] = []
    with pdfplumber.open(path) as document:
        table_rows = [
            cells
            for page in document.pages
            for cells in page.extract_tables()[0][1:]
        ]
    for cells in table_rows:
        rank = as_int(cells[0])
        source = person("F", cells[3], cells[4], OOKA_KEY)
        games: list[int | None] = []
        for score_index, total_index in ((11, 12), (13, 14), (18, 19), (20, 21)):
            games.extend(split_series_scores(cells[score_index], as_int(cells[total_index])))
        total_pin = as_int(cells[9])
        actual = [score for score in games if score is not None]
        if sum(actual) != total_pin:
            raise ValueError(f"Ooka prelim total mismatch: {source['license_no']}")
        aggregate.append(
            aggregate_row(source, rank, total_pin, len(actual), 0, 0, "ooka_prelim.pdf")
        )
        detail.append(detail_row(source, games, "ooka_prelim.pdf"))
    if len(aggregate) != 100:
        raise ValueError(f"Ooka preliminary rows must be 100, got {len(aggregate)}")
    return aggregate, detail


def four_scores(value: Any) -> list[int]:
    scores = [int(item) for item in re.findall(r"\d+", str(value or ""))]
    if len(scores) != 4:
        raise ValueError(f"Four game scores are invalid: {value!r}")
    return scores


def parse_ooka_semifinal(path: Path) -> tuple[list[dict[str, Any]], list[dict[str, Any]]]:
    with pdfplumber.open(path) as document:
        rows = document.pages[0].extract_tables()[0][1:]
    aggregate: list[dict[str, Any]] = []
    detail: list[dict[str, Any]] = []
    for cells in rows:
        source = person("F", cells[1], cells[2], OOKA_KEY)
        games = four_scores(cells[15])
        stage_total = as_int(cells[16])
        cumulative = as_int(cells[9])
        if sum(games) != stage_total or as_int(cells[12]) + stage_total != cumulative:
            raise ValueError(f"Ooka semifinal total mismatch: {source['license_no']}")
        aggregate.append(
            aggregate_row(source, as_int(cells[0]), cumulative, 16, 0, 0, "ooka_semifinal.pdf")
        )
        detail.append(detail_row(source, games, "ooka_semifinal.pdf"))
    if len(aggregate) != 32:
        raise ValueError(f"Ooka semifinal rows must be 32, got {len(aggregate)}")
    return aggregate, detail


def parse_rr_cell(value: Any) -> tuple[int, int]:
    values = [int(item) for item in re.findall(r"\d+", str(value or ""))]
    if len(values) < 2:
        raise ValueError(f"Round-robin cell is invalid: {value!r}")
    return values[0], values[1]


def compact_name(value: Any) -> str:
    return re.sub(r"[\s\u3000]+", "", str(value or ""))


def parse_ooka_round_robin(
    path: Path,
) -> tuple[list[dict[str, Any]], list[dict[str, Any]], list[dict[str, Any]]]:
    with pdfplumber.open(path) as document:
        summary = document.pages[0].extract_tables()[0][1:]
        matrix_table = document.pages[1].extract_tables()[0]
        matrix_header = matrix_table[0]
        matrix = [row for row in matrix_table[1:] if row[0]]
    summary_by_name = {compact_name(row[2]): row for row in summary}
    aggregate: list[dict[str, Any]] = []
    detail: list[dict[str, Any]] = []
    pairings_by_game: dict[int, set[tuple[str, str]]] = {}
    for row in matrix:
        name = str(row[0]).strip()
        summary_row = summary_by_name.get(compact_name(name))
        if summary_row is None:
            raise ValueError(f"Round-robin player cannot be matched: {name}")
        source = person("F", summary_row[1], summary_row[2], OOKA_KEY)
        ordered: dict[int, int] = {}
        for column_index, cell in enumerate(row[3:11], start=3):
            if cell:
                game_number, score = parse_rr_cell(cell)
                ordered[game_number] = score
                opponent_name = compact_name(matrix_header[column_index])
                opponent_summary = summary_by_name.get(opponent_name)
                if opponent_summary is None:
                    raise ValueError(
                        f"Round-robin opponent cannot be matched: {matrix_header[column_index]}"
                    )
                opponent_license = pro_license("F", opponent_summary[1])
                pair = tuple(sorted([source["license_no"], opponent_license]))
                pairings_by_game.setdefault(game_number, set()).add(pair)
        ordered[8] = parse_rr_cell("8\n" + str(row[12]))[1]
        games = [ordered[number] for number in range(1, 9)]
        rr_total = as_int(row[13])
        if sum(games) != rr_total:
            raise ValueError(f"Ooka round-robin total mismatch: {source['license_no']}")
        cumulative = as_int(summary_row[19])
        aggregate.append(
            aggregate_row(source, as_int(summary_row[0]), cumulative, 24, 0, 0, "ooka_rr.pdf")
        )
        detail.append(detail_row(source, games, "ooka_rr.pdf"))
    aggregate.sort(key=lambda row: row["ranking"])
    if len(aggregate) != 8:
        raise ValueError(f"Ooka round-robin rows must be 8, got {len(aggregate)}")
    pairings = []
    for game_number in range(1, 8):
        pairs = sorted(pairings_by_game.get(game_number, set()))
        if len(pairs) != 4 or len({license for pair in pairs for license in pair}) != 8:
            raise ValueError(f"Ooka round-robin game {game_number} pairings are invalid")
        pairings.append(
            {
                "game_number": game_number,
                "pairs": [
                    {"left_license": pair[0], "right_license": pair[1]}
                    for pair in pairs
                ],
            }
        )
    return aggregate, detail, pairings


def parse_ooka_final(
    path: Path,
    semifinal: list[dict[str, Any]],
    round_robin: list[dict[str, Any]],
) -> tuple[list[dict[str, Any]], list[dict[str, int]], list[dict[str, int]]]:
    with pdfplumber.open(path) as document:
        rows = document.pages[1].extract_tables()[0][1:]
    semifinal_by_license = {row["license_no"]: row for row in semifinal}
    rr_by_license = {row["license_no"]: row for row in round_robin}
    final_rows: list[dict[str, Any]] = []
    points: list[dict[str, int]] = []
    prizes: list[dict[str, int]] = []
    for cells in rows:
        if not re.search(r"\d+", str(cells[1] or "")):
            continue
        rank = 1 if "優" in str(cells[0]) else as_int(cells[0])
        source = person("F", cells[1], cells[2], OOKA_KEY)
        base = rr_by_license.get(source["license_no"]) or semifinal_by_license[source["license_no"]]
        point = as_int(cells[6])
        prize = as_int(cells[7])
        final_rows.append(
            aggregate_row(
                source,
                rank,
                int(base["total_pin"]),
                int(base["games"]),
                point,
                prize,
                "ooka_final.pdf",
            )
        )
        points.append({"rank": rank, "points": point})
        prizes.append({"rank": rank, "amount": prize})
    if len(final_rows) != 32:
        raise ValueError(f"Ooka final rows must be 32, got {len(final_rows)}")
    return final_rows, points, prizes


def parse_summer_d(path: Path) -> tuple[dict[str, Any], dict[str, Any]]:
    with pdfplumber.open(path) as document:
        final_table = document.pages[0].extract_tables()[0]
        semifinal_table = document.pages[3].extract_tables()[0]
        prelim_table = document.pages[4].extract_tables()[0]

    prelim_rows: list[dict[str, Any]] = []
    prelim_detail: list[dict[str, Any]] = []
    for cells in prelim_table[1:]:
        source = person("M", cells[2] or cells[1], cells[3], SUMMER_D_KEY)
        games = [None if str(value).upper() == "BL" else as_int(value) for value in cells[7:11]]
        games += [None if str(value).upper() == "BL" else as_int(value) for value in cells[13:17]]
        total_pin = as_int(cells[18])
        actual = [score for score in games if score is not None]
        if sum(actual) != total_pin:
            raise ValueError(f"Summer D prelim total mismatch: {source['license_no']}")
        prelim_rows.append(
            aggregate_row(source, as_int(cells[0]), total_pin, len(actual), 0, 0, "stsu_d_final.pdf")
        )
        prelim_detail.append(
            {
                "rank": as_int(cells[0]),
                "license_no": source["license_no"],
                "display_name": source["display_name"],
                "games": games,
                "games_count": len(actual),
                "total_pin": total_pin,
            }
        )

    semifinal_rows: list[dict[str, Any]] = []
    semifinal_detail: list[dict[str, Any]] = []
    semifinal_by_license: dict[str, dict[str, Any]] = {}
    for cells in semifinal_table[2:]:
        source = person("M", cells[2], cells[4], SUMMER_D_KEY)
        games = four_scores(cells[11])
        stage_total = as_int(cells[12])
        cumulative = as_int(cells[14])
        if sum(games) != stage_total or as_int(cells[8]) + stage_total != cumulative:
            raise ValueError(f"Summer D semifinal total mismatch: {source['license_no']}")
        rank = as_int(cells[0])
        step_points = as_int(cells[1]) if rank > 8 else 0
        aggregate = aggregate_row(
            source, rank, cumulative, 12, step_points, 0, "stsu_d_final.pdf"
        )
        semifinal_rows.append(aggregate)
        semifinal_by_license[source["license_no"]] = aggregate
        semifinal_detail.append(
            {
                "rank": rank,
                "license_no": source["license_no"],
                "display_name": source["display_name"],
                "games": games,
                "games_count": 4,
                "stage_total_pin": stage_total,
                "carry_total_pin": as_int(cells[8]),
                "total_pin": cumulative,
            }
        )

    final_rows: list[dict[str, Any]] = []
    point_distributions: list[dict[str, int]] = []
    prize_distributions: list[dict[str, int]] = []
    for cells in final_table[1:]:
        if not re.search(r"\d+", str(cells[1] or "")):
            continue
        rank = 1 if "優" in str(cells[0]) else as_int(cells[0])
        source = person("M", cells[1], cells[2], SUMMER_D_KEY)
        semifinal = semifinal_by_license[source["license_no"]]
        points = as_int(cells[5])
        prize = as_int(cells[8])
        final_rows.append(
            aggregate_row(source, rank, semifinal["total_pin"], 12, points, prize, "stsu_d_final.pdf")
        )
        point_distributions.append({"rank": rank, "points": points})
        prize_distributions.append({"rank": rank, "amount": prize})

    if len(prelim_rows) != 39 or len(semifinal_rows) != 20 or len(final_rows) != 8:
        raise ValueError("Summer D snapshot row counts are invalid")
    if sum(row["games"] for row in prelim_rows) != 290:
        raise ValueError("Summer D preliminary game count must be 290")

    aggregate_event = {
        "key": SUMMER_D_KEY,
        "existing_tournament_name": SUMMER_D_NAME,
        "point_distributions": point_distributions,
        "prize_distributions": prize_distributions,
        "snapshots": [
            {
                "result_code": "final",
                "result_name": "最終成績",
                "games_count": 12,
                "is_final": True,
                "rows": final_rows,
            },
            {
                "result_code": "semifinal_total",
                "result_name": "準決勝4G・通算12G",
                "games_count": 12,
                "is_final": False,
                "rows": semifinal_rows,
            },
            {
                "result_code": "prelim_total",
                "result_name": "予選8G",
                "games_count": 8,
                "is_final": False,
                "rows": prelim_rows,
            },
        ],
    }
    detail_event = {
        "key": SUMMER_D_KEY,
        "source": {
            "alias": "stsu_d_final.pdf",
            "url": SUMMER_D_URL,
            "sha256": sha256(path),
        },
        "prelim": prelim_detail,
        "semifinal": semifinal_detail,
        "shootout": summer_d_shootout(),
        "validation": {
            "prelim_rows": 39,
            "prelim_game_scores": 290,
            "semifinal_rows": 20,
            "semifinal_game_scores": 80,
            "shootout_game_scores": 10,
        },
    }
    return aggregate_event, detail_event


def frame_rows(marks: list[tuple[str, ...]], cumulative: list[int]) -> list[dict[str, Any]]:
    if len(marks) != 10 or len(cumulative) != 10:
        raise ValueError("A score sheet must contain ten frames")
    rows = []
    previous = 0
    for frame_no, (throws, total) in enumerate(zip(marks, cumulative), start=1):
        padded = list(throws) + [""] * (3 - len(throws))
        rows.append(
            {
                "frame_no": frame_no,
                "throw1": padded[0],
                "throw2": padded[1],
                "throw3": padded[2],
                "frame_score": total - previous,
                "cumulative_score": total,
                "display_marks": {
                    "throw1": padded[0],
                    "throw2": padded[1],
                    "throw3": padded[2],
                },
            }
        )
        previous = total
    return rows


def shootout_player(
    entry_number: str,
    seed: int,
    license_no: str,
    display_name: str,
    score: int,
    winner: bool,
    marks: list[tuple[str, ...]],
    cumulative: list[int],
) -> dict[str, Any]:
    if cumulative[-1] != score:
        raise ValueError(f"Final cumulative score is invalid: {display_name}")
    return {
        "entry_number": entry_number,
        "seed": seed,
        "license_no": license_no,
        "display_name": display_name,
        "score": score,
        "is_winner": winner,
        "frames": frame_rows(marks, cumulative),
        "frame_candidate_count": 1,
        "frame_cumulative_corrections": [],
    }


def summer_d_shootout() -> dict[str, Any]:
    x = ("X",)
    rows = [
        shootout_player("SO:SO1:A", 5, "M00001344", "竹本 圭実", 248, True,
            [("7", "2"), ("9", "/"), x, x, x, x, ("9", "/"), x, x, ("X", "X", "X")],
            [9, 29, 59, 89, 118, 138, 158, 188, 218, 248]),
        shootout_player("SO:SO1:B", 6, "M00001198", "小林 哲也", 247, False,
            [("7", "/"), x, x, ("9", "/"), x, x, x, ("8", "/"), x, ("X", "X", "X")],
            [20, 49, 69, 89, 119, 147, 167, 187, 217, 247]),
        shootout_player("SO:SO1:C", 7, "M00001341", "榎 大成", 166, False,
            [("8", "/"), ("9", "/"), ("9", "/"), ("8", "1"), x, ("6", "2"), ("8", "/"), ("7", "/"), x, ("9", "/", "8")],
            [19, 38, 56, 65, 83, 91, 108, 128, 148, 166]),
        shootout_player("SO:SO1:D", 8, "M00000988", "吉井 昌幸", 170, False,
            [x, ("7", "/"), ("8", "/"), ("8", "1"), ("9", "/"), x, ("8", "/"), ("8", "/"), ("9", "/"), ("9", "-")],
            [20, 38, 56, 65, 85, 105, 123, 142, 161, 170]),
        shootout_player("SO:SO2:A", 2, "M00000365", "西田 久良", 181, False,
            [("8", "/"), x, ("8", "/"), ("7", "2"), x, x, ("7", "2"), x, ("8", "/"), ("X", "8", "/")],
            [20, 40, 57, 66, 93, 112, 121, 141, 161, 181]),
        shootout_player("SO:SO2:B", 3, "M00000905", "太田 隆昌", 222, True,
            [("9", "/"), x, ("9", "/"), ("9", "/"), x, x, ("6", "3"), x, x, ("X", "X", "9")],
            [20, 40, 59, 79, 105, 124, 133, 163, 193, 222]),
        shootout_player("SO:SO2:C", 4, "M00001425", "伊吹 太陽", 189, False,
            [("5", "2"), ("9", "/"), ("9", "/"), ("8", "/"), ("9", "/"), ("9", "/"), ("9", "/"), ("9", "/"), x, ("X", "9", "/")],
            [7, 26, 44, 63, 82, 101, 120, 140, 169, 189]),
        shootout_player("SO:SO2:D", 5, "M00001344", "竹本 圭実", 204, False,
            [x, ("9", "/"), ("9", "/"), x, ("8", "/"), ("9", "/"), x, x, ("9", "/"), ("7", "/", "X")],
            [20, 39, 59, 79, 99, 118, 147, 167, 184, 204]),
        shootout_player("SO:SO3:A", 1, "M00001099", "津島 健次", 202, False,
            [x, x, ("9", "/"), ("7", "2"), x, x, ("8", "/"), x, ("9", "/"), ("9", "/", "X")],
            [29, 49, 66, 75, 103, 123, 143, 163, 182, 202]),
        shootout_player("SO:SO3:B", 3, "M00000905", "太田 隆昌", 203, True,
            [("9", "/"), x, x, ("9", "/"), ("9", "/"), x, x, ("8", "1"), ("9", "/"), ("9", "/", "X")],
            [20, 49, 69, 88, 108, 136, 155, 164, 183, 203]),
    ]
    return {
        "rows": rows,
        "winner_license_no": "M00000905",
        "winner_name": "太田 隆昌",
        "completed_match_count": 3,
    }


def final_player(
    license_no: str,
    display_name: str,
    score: int,
    winner: bool,
    slot: str,
    lane: str,
    marks: list[tuple[str, ...]],
    cumulative: list[int],
) -> dict[str, Any]:
    if cumulative[-1] != score:
        raise ValueError(f"Final sheet total is invalid: {display_name}")
    return {
        "identity": f"P:{license_no}",
        "license_no": license_no,
        "display_name": display_name,
        "score": score,
        "frames": frame_rows(marks, cumulative),
        "frame_candidate_count": 1,
        "frame_cumulative_corrections": [],
        "player_slot": slot,
        "is_winner": winner,
        "lane_label": lane,
    }


def ooka_final_event(final_pdf: Path) -> dict[str, Any]:
    x = ("X",)
    semifinal = {
        "sheet_type": "step_ladder",
        "stage_code": "ステップラダー",
        "match_code": "SL:R1",
        "match_label": "3位決定戦",
        "match_order": 1,
        "round_order": 1,
        "lane_label": "19L / 20L",
        "players": [
            final_player("F00000582", "中島 瑞葵", 201, False, "A", "19L",
                [x, ("9", "/"), x, ("9", "/"), ("8", "/"), ("7", "/"), ("9", "/"), ("9", "/"), ("9", "/"), ("X", "X", "9")],
                [20, 40, 60, 78, 95, 114, 133, 152, 172, 201]),
            final_player("F00000599", "石田 万音", 259, True, "B", "20L",
                [x, x, x, x, x, x, x, ("9", "/"), x, ("9", "/", "X")],
                [30, 60, 90, 120, 150, 179, 199, 219, 239, 259]),
        ],
    }
    final = {
        "sheet_type": "step_ladder",
        "stage_code": "ステップラダー",
        "match_code": "SL:FINAL",
        "match_label": "優勝決定戦",
        "match_order": 2,
        "round_order": 2,
        "lane_label": "19L / 20L",
        "players": [
            final_player("F00000478", "小林あゆみ", 184, True, "A", "19L",
                [("7", "2"), x, x, ("9", "/"), x, ("7", "2"), x, ("8", "/"), ("9", "/"), ("X", "8", "1")],
                [9, 38, 58, 78, 97, 106, 126, 145, 165, 184]),
            final_player("F00000599", "石田 万音", 163, False, "B", "20L",
                [x, x, ("8", "1"), ("8", "-"), ("8", "1"), ("9", "/"), ("8", "/"), ("9", "/"), ("9", "/"), ("9", "/", "5")],
                [28, 47, 56, 64, 73, 91, 110, 129, 148, 163]),
        ],
    }
    return {
        "key": OOKA_KEY,
        "tournament": OOKA_TOURNAMENT,
        "sources": [
            {
                "filename": "FinalResult.pdf",
                "sha256": sha256(final_pdf),
                "url": "https://www.jpba.or.jp/information/tournament/tournament2026/10_TheOpen/Result/FinalResult.pdf",
            }
        ],
        "score_sheets": [semifinal, final],
        "bracket_matches": [],
    }


def main() -> None:
    args = parse_args()
    inputs = [
        args.summer_d_final,
        args.ooka_prelim,
        args.ooka_semifinal,
        args.ooka_round_robin,
        args.ooka_final,
        args.male_ranking,
        args.female_ranking,
    ]
    for path in inputs:
        if not path.is_file() or path.read_bytes()[:5] != b"%PDF-":
            raise FileNotFoundError(f"Official PDF is missing or invalid: {path}")

    aggregate = read_json(args.aggregate)
    season_detail = read_json(args.season_detail)
    standard_detail = read_json(args.standard_detail)
    standard_final = read_json(args.standard_final)

    male_ranking = parse_rankings(args.male_ranking, "M")
    female_ranking = parse_rankings(args.female_ranking, "F")
    if len(male_ranking) != 328 or len(female_ranking) != 212:
        raise ValueError("Late-July ranking row counts must be M=328 and F=212")

    ooka_prelim, ooka_prelim_detail = parse_ooka_prelim(args.ooka_prelim)
    ooka_semifinal, ooka_semifinal_detail = parse_ooka_semifinal(args.ooka_semifinal)
    ooka_rr, ooka_rr_detail, ooka_rr_pairings = parse_ooka_round_robin(args.ooka_round_robin)
    ooka_final, ooka_points_top32, ooka_prizes = parse_ooka_final(
        args.ooka_final, ooka_semifinal, ooka_rr
    )
    standard_points = next(
        event["point_distributions"]
        for event in aggregate["events"]
        if event["key"] == "rokko_2026"
    )
    if standard_points[:32] != ooka_points_top32:
        raise ValueError("Ooka top-32 points differ from the standard official schedule")

    ooka_tournament = {**OOKA_TOURNAMENT, "round_robin_pairings": ooka_rr_pairings}
    ooka_event = {
        "key": OOKA_KEY,
        "tournament": ooka_tournament,
        "point_distributions": standard_points,
        "prize_distributions": ooka_prizes,
        "snapshots": [
            {
                "result_code": "final",
                "result_name": "最終成績",
                "games_count": 26,
                "is_final": True,
                "rows": ooka_final,
            },
            {
                "result_code": "round_robin_total",
                "result_name": "決勝ラウンドロビン8G・通算24G",
                "games_count": 24,
                "is_final": False,
                "rows": ooka_rr,
            },
            {
                "result_code": "semifinal_total",
                "result_name": "準決勝4G・通算16G",
                "games_count": 16,
                "is_final": False,
                "rows": ooka_semifinal,
            },
            {
                "result_code": "prelim_total",
                "result_name": "予選12G",
                "games_count": 12,
                "is_final": False,
                "rows": ooka_prelim,
            },
        ],
    }
    summer_d_event, summer_d_detail = parse_summer_d(args.summer_d_final)
    replace_event(aggregate["events"], ooka_event)
    replace_event(aggregate["events"], summer_d_event)
    aggregate["official_rankings"] = [
        {
            "gender": "M",
            "as_of_date": "2026-07-28",
            "source_url": "https://www.jpba.or.jp/information/tournament/ranking/2026/M_PointRanking_0728.pdf",
            "rows": male_ranking,
        },
        {
            "gender": "F",
            "as_of_date": "2026-07-27",
            "source_url": "https://www.jpba.or.jp/information/tournament/ranking/2026/W_PointRanking_0727.pdf",
            "rows": female_ranking,
        },
    ]
    aggregate["source_checked_at"] = "2026-07-28"
    aggregate["source_sha256"].update(
        {
            "stsu_d_final.pdf": sha256(args.summer_d_final),
            "ooka_prelim.pdf": sha256(args.ooka_prelim),
            "ooka_semifinal.pdf": sha256(args.ooka_semifinal),
            "ooka_rr.pdf": sha256(args.ooka_round_robin),
            "ooka_final.pdf": sha256(args.ooka_final),
            "rank_m.pdf": sha256(args.male_ranking),
            "rank_f.pdf": sha256(args.female_ranking),
        }
    )

    replace_event(season_detail["events"], summer_d_detail)
    season_detail["source_checked_at"] = "2026-07-28"

    ooka_sources = [
        {
            "alias": "ooka_prelim.pdf",
            "filename": "AB_12G.pdf",
            "sha256": sha256(args.ooka_prelim),
            "aggregate_hash_verified": True,
            "url": "https://www.jpba.or.jp/information/tournament/tournament2026/10_TheOpen/Result/AB_12G.pdf",
        },
        {
            "alias": "ooka_semifinal.pdf",
            "filename": "Semifinal_4G.pdf",
            "sha256": sha256(args.ooka_semifinal),
            "aggregate_hash_verified": True,
            "url": "https://www.jpba.or.jp/information/tournament/tournament2026/10_TheOpen/Result/Semifinal_4G.pdf",
        },
        {
            "alias": "ooka_rr.pdf",
            "filename": "RR8G_Result.pdf",
            "sha256": sha256(args.ooka_round_robin),
            "aggregate_hash_verified": True,
            "url": "https://www.jpba.or.jp/information/tournament/tournament2026/10_TheOpen/Result/RR8G_Result.pdf",
        },
    ]
    ooka_standard_detail = {
        "key": OOKA_KEY,
        "tournament": ooka_tournament,
        "sources": ooka_sources,
        "stages": [
            {"stage": "予選", "rows": ooka_prelim_detail},
            {"stage": "準決勝", "rows": ooka_semifinal_detail},
            {"stage": "ラウンドロビン", "rows": ooka_rr_detail},
        ],
        "expected_score_count": sum(
            row["games_count"]
            for row in ooka_prelim_detail + ooka_semifinal_detail + ooka_rr_detail
        ),
        "expected_player_count": 100,
    }
    if ooka_standard_detail["expected_score_count"] != 1383:
        raise ValueError("Ooka per-game score count must be 1,383")
    replace_event(standard_detail["events"], ooka_standard_detail)
    standard_detail["event_count"] = len(standard_detail["events"])
    standard_detail["source_checked_at"] = "2026-07-28"

    replace_event(standard_final["events"], ooka_final_event(args.ooka_final))
    standard_final["event_count"] = len(standard_final["events"])
    standard_final["score_sheet_count"] = sum(
        len(event.get("score_sheets", [])) for event in standard_final["events"]
    )
    standard_final["frame_player_count"] = sum(
        len(sheet.get("players", []))
        for event in standard_final["events"]
        for sheet in event.get("score_sheets", [])
    )
    standard_final["frame_count"] = sum(
        len(player.get("frames", []))
        for event in standard_final["events"]
        for sheet in event.get("score_sheets", [])
        for player in sheet.get("players", [])
    )
    standard_final["source_checked_at"] = "2026-07-28"

    report = {
        "aggregate_events": len(aggregate["events"]),
        "aggregate_snapshots": sum(len(event["snapshots"]) for event in aggregate["events"]),
        "male_ranking_rows": len(male_ranking),
        "female_ranking_rows": len(female_ranking),
        "summer_d_prelim_scores": sum(row["games_count"] for row in summer_d_detail["prelim"]),
        "summer_d_semifinal_scores": sum(row["games_count"] for row in summer_d_detail["semifinal"]),
        "summer_d_shootout_scores": len(summer_d_detail["shootout"]["rows"]),
        "standard_detail_events": len(standard_detail["events"]),
        "standard_detail_scores": sum(
            event["expected_score_count"] for event in standard_detail["events"]
        ),
        "standard_final_events": len(standard_final["events"]),
        "standard_final_sheets": standard_final["score_sheet_count"],
        "standard_final_frame_players": standard_final["frame_player_count"],
        "standard_final_frames": standard_final["frame_count"],
        "write": args.write,
    }
    print(json.dumps(report, ensure_ascii=False, indent=2))

    if args.write:
        write_json(args.aggregate, aggregate)
        write_json(args.season_detail, season_detail)
        write_json(args.standard_detail, standard_detail)
        write_json(args.standard_final, standard_final)


if __name__ == "__main__":
    main()
