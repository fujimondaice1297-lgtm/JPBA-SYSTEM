--
-- PostgreSQL database dump
--

\restrict 9dYDtdLD6BQvaxUZ9lVJM23nldifk869IQwAQanWuxNCOB6qGaWY7IbI9vLVuXx

-- Dumped from database version 18.2
-- Dumped by pg_dump version 18.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: annual_dues; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.annual_dues (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    year smallint NOT NULL,
    paid_at date,
    note character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.annual_dues OWNER TO postgres;

--
-- Name: annual_dues_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.annual_dues_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.annual_dues_id_seq OWNER TO postgres;

--
-- Name: annual_dues_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.annual_dues_id_seq OWNED BY public.annual_dues.id;


--
-- Name: approved_ball_pro_bowler; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.approved_ball_pro_bowler (
    id bigint NOT NULL,
    pro_bowler_license_no character varying(255) NOT NULL,
    approved_ball_id bigint NOT NULL,
    year integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    pro_bowler_id bigint
);


ALTER TABLE public.approved_ball_pro_bowler OWNER TO postgres;

--
-- Name: approved_ball_pro_bowler_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.approved_ball_pro_bowler_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.approved_ball_pro_bowler_id_seq OWNER TO postgres;

--
-- Name: approved_ball_pro_bowler_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.approved_ball_pro_bowler_id_seq OWNED BY public.approved_ball_pro_bowler.id;


--
-- Name: approved_balls; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.approved_balls (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    name_kana character varying(255),
    manufacturer character varying(255) NOT NULL,
    approved boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    release_date date
);


ALTER TABLE public.approved_balls OWNER TO postgres;

--
-- Name: approved_balls_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.approved_balls_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.approved_balls_id_seq OWNER TO postgres;

--
-- Name: approved_balls_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.approved_balls_id_seq OWNED BY public.approved_balls.id;


--
-- Name: area; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.area (
    id bigint NOT NULL,
    name text NOT NULL,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.area OWNER TO postgres;

--
-- Name: COLUMN area.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.area.name IS '地区名';


--
-- Name: area_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.area_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.area_id_seq OWNER TO postgres;

--
-- Name: area_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.area_id_seq OWNED BY public.area.id;


--
-- Name: ball_info; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ball_info (
    id bigint NOT NULL,
    brand character varying(255),
    model character varying(255),
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.ball_info OWNER TO postgres;

--
-- Name: COLUMN ball_info.brand; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.ball_info.brand IS 'ブランド名（例：Storm）';


--
-- Name: COLUMN ball_info.model; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.ball_info.model IS 'モデル名（例：Phaze II）';


--
-- Name: ball_info_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ball_info_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ball_info_id_seq OWNER TO postgres;

--
-- Name: ball_info_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ball_info_id_seq OWNED BY public.ball_info.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO postgres;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO postgres;

--
-- Name: calendar_days; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.calendar_days (
    date date NOT NULL,
    holiday_name character varying(255),
    is_holiday boolean DEFAULT false NOT NULL,
    rokuyou character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.calendar_days OWNER TO postgres;

--
-- Name: calendar_events; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.calendar_events (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    venue character varying(255),
    kind character varying(255) DEFAULT 'other'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT calendar_events_kind_check CHECK (((kind)::text = ANY ((ARRAY['pro_test'::character varying, 'approved'::character varying, 'other'::character varying])::text[])))
);


ALTER TABLE public.calendar_events OWNER TO postgres;

--
-- Name: calendar_events_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.calendar_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.calendar_events_id_seq OWNER TO postgres;

--
-- Name: calendar_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.calendar_events_id_seq OWNED BY public.calendar_events.id;


--
-- Name: distribution_patterns; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.distribution_patterns (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    name character varying(255) DEFAULT '未設定'::character varying NOT NULL,
    type character varying(255) DEFAULT '未設定'::character varying NOT NULL
);


ALTER TABLE public.distribution_patterns OWNER TO postgres;

--
-- Name: distribution_patterns_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.distribution_patterns_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.distribution_patterns_id_seq OWNER TO postgres;

--
-- Name: distribution_patterns_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.distribution_patterns_id_seq OWNED BY public.distribution_patterns.id;


--
-- Name: districts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.districts (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    label character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.districts OWNER TO postgres;

--
-- Name: districts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.districts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.districts_id_seq OWNER TO postgres;

--
-- Name: districts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.districts_id_seq OWNED BY public.districts.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: flash_news; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.flash_news (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    url character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.flash_news OWNER TO postgres;

--
-- Name: flash_news_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.flash_news_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.flash_news_id_seq OWNER TO postgres;

--
-- Name: flash_news_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.flash_news_id_seq OWNED BY public.flash_news.id;


--
-- Name: game_scores; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.game_scores (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    stage character varying(255) NOT NULL,
    license_number character varying(255),
    name character varying(255),
    entry_number character varying(255),
    game_number integer NOT NULL,
    score integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    shift character varying(255),
    gender character varying(1),
    pro_bowler_id bigint
);


ALTER TABLE public.game_scores OWNER TO postgres;

--
-- Name: game_scores_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.game_scores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.game_scores_id_seq OWNER TO postgres;

--
-- Name: game_scores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.game_scores_id_seq OWNED BY public.game_scores.id;


--
-- Name: group_mail_recipients; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_mail_recipients (
    id bigint NOT NULL,
    mailout_id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    email character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'queued'::character varying NOT NULL,
    sent_at timestamp(0) without time zone,
    error_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.group_mail_recipients OWNER TO postgres;

--
-- Name: group_mail_recipients_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_mail_recipients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_mail_recipients_id_seq OWNER TO postgres;

--
-- Name: group_mail_recipients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_mail_recipients_id_seq OWNED BY public.group_mail_recipients.id;


--
-- Name: group_mailouts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_mailouts (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    sender_user_id bigint NOT NULL,
    subject character varying(255) NOT NULL,
    body text NOT NULL,
    from_address character varying(255),
    from_name character varying(255),
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    sent_count integer DEFAULT 0 NOT NULL,
    fail_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.group_mailouts OWNER TO postgres;

--
-- Name: group_mailouts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_mailouts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_mailouts_id_seq OWNER TO postgres;

--
-- Name: group_mailouts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_mailouts_id_seq OWNED BY public.group_mailouts.id;


--
-- Name: group_members; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_members (
    id bigint NOT NULL,
    group_id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    source character varying(255) DEFAULT 'rule'::character varying NOT NULL,
    assigned_at timestamp(0) without time zone,
    expires_at date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT group_members_source_check CHECK (((source)::text = ANY ((ARRAY['rule'::character varying, 'manual'::character varying, 'snapshot'::character varying])::text[])))
);


ALTER TABLE public.group_members OWNER TO postgres;

--
-- Name: group_members_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_members_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_members_id_seq OWNER TO postgres;

--
-- Name: group_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_members_id_seq OWNED BY public.group_members.id;


--
-- Name: groups; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.groups (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    rule_json json,
    retention character varying(255) DEFAULT 'forever'::character varying NOT NULL,
    expires_at date,
    show_on_mypage boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    preset character varying(255),
    action_mypage boolean DEFAULT false NOT NULL,
    action_email boolean DEFAULT false NOT NULL,
    action_postal boolean DEFAULT false NOT NULL,
    CONSTRAINT groups_retention_check CHECK (((retention)::text = ANY ((ARRAY['forever'::character varying, 'fye'::character varying, 'until'::character varying])::text[]))),
    CONSTRAINT groups_type_check CHECK (((type)::text = ANY ((ARRAY['rule'::character varying, 'snapshot'::character varying])::text[])))
);


ALTER TABLE public.groups OWNER TO postgres;

--
-- Name: groups_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.groups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.groups_id_seq OWNER TO postgres;

--
-- Name: groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.groups_id_seq OWNED BY public.groups.id;


--
-- Name: hof_inductions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.hof_inductions (
    id bigint NOT NULL,
    pro_id bigint NOT NULL,
    year smallint NOT NULL,
    citation text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone,
    CONSTRAINT hof_inductions_year_check CHECK (((year >= 1900) AND (year <= 2100)))
);


ALTER TABLE public.hof_inductions OWNER TO postgres;

--
-- Name: hof_inductions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.hof_inductions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.hof_inductions_id_seq OWNER TO postgres;

--
-- Name: hof_inductions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.hof_inductions_id_seq OWNED BY public.hof_inductions.id;


--
-- Name: hof_photos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.hof_photos (
    id bigint NOT NULL,
    hof_id bigint NOT NULL,
    url text NOT NULL,
    credit character varying(255),
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


ALTER TABLE public.hof_photos OWNER TO postgres;

--
-- Name: hof_photos_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.hof_photos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.hof_photos_id_seq OWNER TO postgres;

--
-- Name: hof_photos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.hof_photos_id_seq OWNED BY public.hof_photos.id;


--
-- Name: information_files; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.information_files (
    id bigint NOT NULL,
    information_id bigint NOT NULL,
    type character varying(32) NOT NULL,
    title character varying(255),
    file_path character varying(255) NOT NULL,
    visibility character varying(16) DEFAULT 'public'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.information_files OWNER TO postgres;

--
-- Name: information_files_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.information_files_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.information_files_id_seq OWNER TO postgres;

--
-- Name: information_files_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.information_files_id_seq OWNED BY public.information_files.id;


--
-- Name: informations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.informations (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    body text NOT NULL,
    is_public boolean DEFAULT true NOT NULL,
    starts_at timestamp(0) without time zone,
    ends_at timestamp(0) without time zone,
    audience character varying(255) DEFAULT 'public'::character varying NOT NULL,
    required_training_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    category character varying(32) DEFAULT 'NEWS'::character varying NOT NULL,
    published_at timestamp(0) without time zone,
    CONSTRAINT informations_audience_check CHECK (((audience)::text = ANY ((ARRAY['public'::character varying, 'members'::character varying, 'district_leaders'::character varying, 'needs_training'::character varying])::text[]))),
    CONSTRAINT informations_category_check CHECK (((category IS NULL) OR ((category)::text = ANY ((ARRAY['NEWS'::character varying, 'イベント'::character varying, '大会'::character varying, 'ｲﾝｽﾄﾗｸﾀｰ'::character varying])::text[]))))
);


ALTER TABLE public.informations OWNER TO postgres;

--
-- Name: informations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.informations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.informations_id_seq OWNER TO postgres;

--
-- Name: informations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.informations_id_seq OWNED BY public.informations.id;


--
-- Name: instructor_registry; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.instructor_registry (
    id bigint NOT NULL,
    source_type character varying(64) NOT NULL,
    source_key character varying(255) NOT NULL,
    legacy_instructor_license_no character varying(255),
    pro_bowler_id bigint,
    license_no character varying(255),
    cert_no character varying(255),
    name character varying(255) NOT NULL,
    name_kana character varying(255),
    sex boolean,
    district_id bigint,
    instructor_category character varying(32) NOT NULL,
    grade character varying(255),
    coach_qualification boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    is_visible boolean DEFAULT true NOT NULL,
    last_synced_at timestamp(0) without time zone,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    source_registered_at timestamp(0) without time zone,
    is_current boolean DEFAULT true NOT NULL,
    superseded_at timestamp(0) without time zone,
    supersede_reason character varying(64),
    renewal_year smallint,
    renewal_due_on date,
    renewal_status character varying(16),
    renewed_at date,
    renewal_note text,
    CONSTRAINT instructor_registry_category_check CHECK (((instructor_category)::text = ANY ((ARRAY['pro_bowler'::character varying, 'pro_instructor'::character varying, 'certified'::character varying])::text[]))),
    CONSTRAINT instructor_registry_grade_check CHECK (((grade IS NULL) OR ((grade)::text = ANY ((ARRAY['C級'::character varying, '準B級'::character varying, 'B級'::character varying, '準A級'::character varying, 'A級'::character varying, '2級'::character varying, '1級'::character varying])::text[])))),
    CONSTRAINT instructor_registry_renewal_status_check CHECK (((renewal_status IS NULL) OR ((renewal_status)::text = ANY ((ARRAY['pending'::character varying, 'renewed'::character varying, 'expired'::character varying])::text[]))))
);


ALTER TABLE public.instructor_registry OWNER TO postgres;

--
-- Name: COLUMN instructor_registry.source_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.source_type IS '取込元種別（legacy_instructors / pro_bowler / manual など）';


--
-- Name: COLUMN instructor_registry.source_key; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.source_key IS 'source_type 内で一意なキー';


--
-- Name: COLUMN instructor_registry.legacy_instructor_license_no; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.legacy_instructor_license_no IS '旧 instructors.license_no の退避';


--
-- Name: COLUMN instructor_registry.license_no; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.license_no IS 'ライセンス番号';


--
-- Name: COLUMN instructor_registry.cert_no; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.cert_no IS '認定番号';


--
-- Name: COLUMN instructor_registry.sex; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.sex IS '男性=true / 女性=false / 不明=null';


--
-- Name: COLUMN instructor_registry.instructor_category; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.instructor_category IS 'pro_bowler / pro_instructor / certified';


--
-- Name: COLUMN instructor_registry.grade; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.grade IS 'C級 / 準B級 / B級 / 準A級 / A級 / 2級 / 1級';


--
-- Name: COLUMN instructor_registry.last_synced_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.last_synced_at IS '最終同期日時';


--
-- Name: COLUMN instructor_registry.notes; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.notes IS '備考';


--
-- Name: COLUMN instructor_registry.source_registered_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.source_registered_at IS '元データ上の登録日・交付日・開始日';


--
-- Name: COLUMN instructor_registry.is_current; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.is_current IS '現在有効な所属状態か';


--
-- Name: COLUMN instructor_registry.superseded_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.superseded_at IS '後続状態に置き換わった日時';


--
-- Name: COLUMN instructor_registry.supersede_reason; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.supersede_reason IS 'promoted_to_pro_bowler / promoted_to_pro_instructor / downgraded_to_certified など';


--
-- Name: COLUMN instructor_registry.renewal_year; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.renewal_year IS '更新対象年度';


--
-- Name: COLUMN instructor_registry.renewal_due_on; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.renewal_due_on IS '更新期限（原則 12/31）';


--
-- Name: COLUMN instructor_registry.renewal_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.renewal_status IS 'pending / renewed / expired';


--
-- Name: COLUMN instructor_registry.renewed_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.renewed_at IS '更新完了日';


--
-- Name: COLUMN instructor_registry.renewal_note; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.instructor_registry.renewal_note IS '更新備考';


--
-- Name: instructor_registry_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.instructor_registry_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.instructor_registry_id_seq OWNER TO postgres;

--
-- Name: instructor_registry_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.instructor_registry_id_seq OWNED BY public.instructor_registry.id;


--
-- Name: instructors; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.instructors (
    license_no character varying(255) NOT NULL,
    pro_bowler_id bigint,
    name character varying(255) NOT NULL,
    name_kana character varying(255),
    sex boolean NOT NULL,
    district_id character varying(255),
    instructor_type character varying(255) NOT NULL,
    grade character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    is_visible boolean DEFAULT true NOT NULL,
    coach_qualification boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT instructors_grade_check CHECK (((grade IS NULL) OR ((grade)::text = ANY ((ARRAY['C級'::character varying, '準B級'::character varying, 'B級'::character varying, '準A級'::character varying, 'A級'::character varying, '2級'::character varying, '1級'::character varying])::text[])))),
    CONSTRAINT instructors_instructor_type_check CHECK (((instructor_type)::text = ANY ((ARRAY['pro'::character varying, 'certified'::character varying])::text[])))
);


ALTER TABLE public.instructors OWNER TO postgres;

--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO postgres;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: kaiin_status; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.kaiin_status (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    reg_date timestamp(0) without time zone,
    del_flg boolean DEFAULT false NOT NULL,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255),
    is_retired boolean DEFAULT false NOT NULL
);


ALTER TABLE public.kaiin_status OWNER TO postgres;

--
-- Name: COLUMN kaiin_status.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.kaiin_status.name IS '会員ステータス';


--
-- Name: COLUMN kaiin_status.reg_date; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.kaiin_status.reg_date IS '登録日時';


--
-- Name: COLUMN kaiin_status.del_flg; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.kaiin_status.del_flg IS '削除フラグ';


--
-- Name: kaiin_status_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.kaiin_status_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.kaiin_status_id_seq OWNER TO postgres;

--
-- Name: kaiin_status_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.kaiin_status_id_seq OWNED BY public.kaiin_status.id;


--
-- Name: license; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.license (
    id bigint NOT NULL,
    name text NOT NULL,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.license OWNER TO postgres;

--
-- Name: COLUMN license.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.license.name IS 'ライセンス名';


--
-- Name: license_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.license_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.license_id_seq OWNER TO postgres;

--
-- Name: license_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.license_id_seq OWNED BY public.license.id;


--
-- Name: match_videos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.match_videos (
    id bigint NOT NULL,
    video_url text NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.match_videos OWNER TO postgres;

--
-- Name: match_videos_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.match_videos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.match_videos_id_seq OWNER TO postgres;

--
-- Name: match_videos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.match_videos_id_seq OWNED BY public.match_videos.id;


--
-- Name: media_publications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.media_publications (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    url text NOT NULL,
    published_at date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.media_publications OWNER TO postgres;

--
-- Name: media_publications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.media_publications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.media_publications_id_seq OWNER TO postgres;

--
-- Name: media_publications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.media_publications_id_seq OWNED BY public.media_publications.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: organization_masters; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.organization_masters (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    url character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.organization_masters OWNER TO postgres;

--
-- Name: organization_masters_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.organization_masters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.organization_masters_id_seq OWNER TO postgres;

--
-- Name: organization_masters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.organization_masters_id_seq OWNED BY public.organization_masters.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO postgres;

--
-- Name: place; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.place (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(255),
    phone character varying(255),
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.place OWNER TO postgres;

--
-- Name: COLUMN place.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.place.name IS '会場名（例：〇〇ボウル）';


--
-- Name: COLUMN place.address; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.place.address IS '住所';


--
-- Name: COLUMN place.phone; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.place.phone IS '電話番号';


--
-- Name: place_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.place_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.place_id_seq OWNER TO postgres;

--
-- Name: place_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.place_id_seq OWNED BY public.place.id;


--
-- Name: point_distributions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.point_distributions (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    rank integer NOT NULL,
    points integer NOT NULL,
    pattern_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.point_distributions OWNER TO postgres;

--
-- Name: point_distributions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.point_distributions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.point_distributions_id_seq OWNER TO postgres;

--
-- Name: point_distributions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.point_distributions_id_seq OWNED BY public.point_distributions.id;


--
-- Name: prize_distributions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.prize_distributions (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    rank integer NOT NULL,
    amount integer NOT NULL,
    pattern_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.prize_distributions OWNER TO postgres;

--
-- Name: prize_distributions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.prize_distributions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.prize_distributions_id_seq OWNER TO postgres;

--
-- Name: prize_distributions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.prize_distributions_id_seq OWNED BY public.prize_distributions.id;


--
-- Name: pro_bowler_biographies; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_bowler_biographies (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    motto character varying(255),
    message text,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.pro_bowler_biographies OWNER TO postgres;

--
-- Name: COLUMN pro_bowler_biographies.pro_bowler_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_biographies.pro_bowler_id IS 'pro_bowlersテーブルのID';


--
-- Name: pro_bowler_biographies_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_bowler_biographies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_bowler_biographies_id_seq OWNER TO postgres;

--
-- Name: pro_bowler_biographies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_bowler_biographies_id_seq OWNED BY public.pro_bowler_biographies.id;


--
-- Name: pro_bowler_instructor_info; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_bowler_instructor_info (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    instructor_flag boolean DEFAULT false NOT NULL,
    lesson_center character varying(255),
    lesson_notes text,
    certifications text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.pro_bowler_instructor_info OWNER TO postgres;

--
-- Name: COLUMN pro_bowler_instructor_info.pro_bowler_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_instructor_info.pro_bowler_id IS 'pro_bowlersテーブルのID';


--
-- Name: COLUMN pro_bowler_instructor_info.certifications; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_instructor_info.certifications IS '資格など（例: 公認アシスタントマネージャー）';


--
-- Name: pro_bowler_instructor_info_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_bowler_instructor_info_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_bowler_instructor_info_id_seq OWNER TO postgres;

--
-- Name: pro_bowler_instructor_info_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_bowler_instructor_info_id_seq OWNED BY public.pro_bowler_instructor_info.id;


--
-- Name: pro_bowler_links; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_bowler_links (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    homepage_url character varying(255),
    twitter_url character varying(255),
    instagram_url character varying(255),
    youtube_url character varying(255),
    facebook_url character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.pro_bowler_links OWNER TO postgres;

--
-- Name: COLUMN pro_bowler_links.pro_bowler_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_links.pro_bowler_id IS 'pro_bowlersテーブルのID';


--
-- Name: pro_bowler_links_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_bowler_links_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_bowler_links_id_seq OWNER TO postgres;

--
-- Name: pro_bowler_links_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_bowler_links_id_seq OWNED BY public.pro_bowler_links.id;


--
-- Name: pro_bowler_profiles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_bowler_profiles (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    birthdate date,
    birthplace character varying(255),
    height_cm integer,
    weight_kg integer,
    blood_type character varying(255),
    home_zip character varying(255),
    home_address character varying(255),
    phone_home character varying(255),
    work_zip character varying(255),
    work_address character varying(255),
    work_place character varying(255),
    phone_work character varying(255),
    work_place_url character varying(255),
    phone_mobile character varying(255),
    fax_number character varying(255),
    email character varying(255),
    image_path character varying(255),
    public_image_path character varying(255),
    qr_code_path character varying(255),
    mailing_preference smallint,
    license_issue_date date,
    pro_entry_year integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.pro_bowler_profiles OWNER TO postgres;

--
-- Name: COLUMN pro_bowler_profiles.pro_bowler_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_profiles.pro_bowler_id IS 'pro_bowlersテーブルのID';


--
-- Name: COLUMN pro_bowler_profiles.work_place_url; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_profiles.work_place_url IS '勤務先URL';


--
-- Name: COLUMN pro_bowler_profiles.image_path; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_profiles.image_path IS '非公開用プロフィール画像';


--
-- Name: COLUMN pro_bowler_profiles.public_image_path; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_profiles.public_image_path IS '公開用プロフィール画像';


--
-- Name: COLUMN pro_bowler_profiles.qr_code_path; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_profiles.qr_code_path IS 'QRコード画像パス';


--
-- Name: COLUMN pro_bowler_profiles.mailing_preference; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_profiles.mailing_preference IS '郵送区分: 1=自宅, 2=勤務先';


--
-- Name: COLUMN pro_bowler_profiles.license_issue_date; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_profiles.license_issue_date IS 'ライセンス交付日';


--
-- Name: COLUMN pro_bowler_profiles.pro_entry_year; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_profiles.pro_entry_year IS 'プロ入り年';


--
-- Name: pro_bowler_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_bowler_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_bowler_profiles_id_seq OWNER TO postgres;

--
-- Name: pro_bowler_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_bowler_profiles_id_seq OWNED BY public.pro_bowler_profiles.id;


--
-- Name: pro_bowler_sponsors; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_bowler_sponsors (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    sponsor_name character varying(255) NOT NULL,
    sponsor_note character varying(255),
    start_year integer,
    end_year integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.pro_bowler_sponsors OWNER TO postgres;

--
-- Name: COLUMN pro_bowler_sponsors.pro_bowler_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowler_sponsors.pro_bowler_id IS 'pro_bowlersテーブルのID';


--
-- Name: pro_bowler_sponsors_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_bowler_sponsors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_bowler_sponsors_id_seq OWNER TO postgres;

--
-- Name: pro_bowler_sponsors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_bowler_sponsors_id_seq OWNED BY public.pro_bowler_sponsors.id;


--
-- Name: pro_bowler_titles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_bowler_titles (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    tournament_id bigint,
    title_name character varying(255) NOT NULL,
    year smallint NOT NULL,
    won_date date,
    source character varying(255) DEFAULT 'manual'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tournament_name character varying(255)
);


ALTER TABLE public.pro_bowler_titles OWNER TO postgres;

--
-- Name: pro_bowler_titles_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_bowler_titles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_bowler_titles_id_seq OWNER TO postgres;

--
-- Name: pro_bowler_titles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_bowler_titles_id_seq OWNED BY public.pro_bowler_titles.id;


--
-- Name: pro_bowler_trainings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_bowler_trainings (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    training_id bigint NOT NULL,
    completed_at date NOT NULL,
    expires_at date NOT NULL,
    proof_path character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.pro_bowler_trainings OWNER TO postgres;

--
-- Name: pro_bowler_trainings_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_bowler_trainings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_bowler_trainings_id_seq OWNER TO postgres;

--
-- Name: pro_bowler_trainings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_bowler_trainings_id_seq OWNED BY public.pro_bowler_trainings.id;


--
-- Name: pro_bowlers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_bowlers (
    id bigint NOT NULL,
    license_no character varying(255) NOT NULL,
    name_kanji text,
    name_kana text,
    sex bigint DEFAULT '0'::smallint NOT NULL,
    district_id bigint,
    acquire_date date,
    is_active boolean DEFAULT true NOT NULL,
    is_visible boolean DEFAULT true NOT NULL,
    coach_qualification boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    kibetsu smallint,
    membership_type character varying(255),
    license_issue_date date,
    phone_home character varying(20),
    has_title boolean DEFAULT false NOT NULL,
    is_district_leader boolean DEFAULT false NOT NULL,
    has_sports_coach_license boolean DEFAULT false NOT NULL,
    sports_coach_name character varying(255),
    birthdate date,
    birthplace character varying(255),
    height_cm integer,
    weight_kg integer,
    blood_type character varying(3),
    home_zip character varying(10),
    home_address character varying(255),
    work_zip character varying(10),
    work_address character varying(255),
    organization_url character varying(255),
    phone_work character varying(20),
    phone_mobile character varying(20),
    fax_number character varying(20),
    email character varying(255),
    image_path character varying(255),
    public_image_path character varying(255),
    qr_code_path character varying(255),
    mailing_preference smallint,
    pro_entry_year smallint,
    hobby character varying(255),
    bowling_history character varying(255),
    other_sports_history text,
    season_goal character varying(255),
    coach character varying(255),
    selling_point text,
    free_comment text,
    facebook character varying(255),
    twitter character varying(255),
    instagram character varying(255),
    rankseeker character varying(255),
    jbc_driller_cert character varying(255),
    a_license_date date,
    permanent_seed_date date,
    hall_of_fame_date date,
    birthdate_public date,
    memo text,
    usbc_coach character varying(255),
    a_class_status character varying(255),
    a_class_year character varying(255),
    b_class_status character varying(255),
    b_class_year character varying(255),
    c_class_status character varying(255),
    c_class_year character varying(255),
    master_status character varying(255),
    master_year character varying(255),
    coach_4_status character varying(255),
    coach_4_year character varying(255),
    coach_3_status character varying(255),
    coach_3_year character varying(255),
    coach_1_status character varying(255),
    coach_1_year character varying(255),
    kenkou_status character varying(255),
    kenkou_year character varying(255),
    school_license_status character varying(255),
    school_license_year character varying(255),
    license_no_num integer GENERATED ALWAYS AS ((NULLIF(regexp_replace((license_no)::text, '[^0-9]'::text, ''::text, 'g'::text), ''::text))::integer) STORED,
    titles_count integer DEFAULT 0 NOT NULL,
    perfect_count integer DEFAULT 0 NOT NULL,
    seven_ten_count integer DEFAULT 0 NOT NULL,
    eight_hundred_count integer DEFAULT 0 NOT NULL,
    award_total_count integer DEFAULT 0 NOT NULL,
    organization_name character varying(255),
    organization_zip character varying(10),
    organization_addr1 character varying(255),
    organization_addr2 character varying(255),
    public_zip character varying(10),
    public_addr1 character varying(255),
    public_addr2 character varying(255),
    public_addr_same_as_org boolean DEFAULT false,
    mailing_zip character varying(10),
    mailing_addr1 character varying(255),
    mailing_addr2 character varying(255),
    mailing_addr_same_as_org boolean DEFAULT false,
    password_change_status smallint DEFAULT 2,
    login_id character varying(255),
    mypage_temp_password character varying(255),
    height_is_public boolean DEFAULT false,
    weight_is_public boolean DEFAULT false,
    blood_type_is_public boolean DEFAULT false,
    dominant_arm character varying(5),
    motto character varying(255),
    equipment_contract character varying(255),
    coaching_history text,
    sponsor_a character varying(255),
    sponsor_a_url character varying(255),
    sponsor_b character varying(255),
    sponsor_b_url character varying(255),
    sponsor_c character varying(255),
    sponsor_c_url character varying(255),
    association_role character varying(255),
    a_license_number integer,
    birthdate_public_hide_year boolean DEFAULT false NOT NULL,
    birthdate_public_is_private boolean DEFAULT false NOT NULL,
    member_class character varying(32) DEFAULT 'player'::character varying NOT NULL,
    can_enter_official_tournament boolean DEFAULT true NOT NULL,
    CONSTRAINT pro_bowlers_member_class_check CHECK (((member_class)::text = ANY ((ARRAY['player'::character varying, 'pro_instructor'::character varying, 'honorary_or_overseas'::character varying, 'other'::character varying])::text[])))
);


ALTER TABLE public.pro_bowlers OWNER TO postgres;

--
-- Name: COLUMN pro_bowlers.license_no; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.license_no IS 'ライセンスNo.';


--
-- Name: COLUMN pro_bowlers.name_kanji; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.name_kanji IS '氏名（漢字）';


--
-- Name: COLUMN pro_bowlers.name_kana; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.name_kana IS '氏名（カナ）';


--
-- Name: COLUMN pro_bowlers.sex; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.sex IS '性別 0=不明,1=男,2=女';


--
-- Name: COLUMN pro_bowlers.district_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.district_id IS '地区ID';


--
-- Name: COLUMN pro_bowlers.acquire_date; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.acquire_date IS '取得日';


--
-- Name: COLUMN pro_bowlers.is_active; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.is_active IS '有効フラグ';


--
-- Name: COLUMN pro_bowlers.is_visible; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.is_visible IS '表示フラグ';


--
-- Name: COLUMN pro_bowlers.coach_qualification; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.coach_qualification IS 'コーチ資格';


--
-- Name: COLUMN pro_bowlers.kibetsu; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.kibetsu IS '期別';


--
-- Name: COLUMN pro_bowlers.membership_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.membership_type IS '会員種別';


--
-- Name: COLUMN pro_bowlers.hobby; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.hobby IS '趣味・特技';


--
-- Name: COLUMN pro_bowlers.bowling_history; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.bowling_history IS 'ボウリング歴';


--
-- Name: COLUMN pro_bowlers.other_sports_history; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.other_sports_history IS '他スポーツ歴';


--
-- Name: COLUMN pro_bowlers.season_goal; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.season_goal IS '今シーズン目標';


--
-- Name: COLUMN pro_bowlers.coach; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.coach IS '師匠・コーチ';


--
-- Name: COLUMN pro_bowlers.selling_point; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.selling_point IS 'セールスポイント';


--
-- Name: COLUMN pro_bowlers.free_comment; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.free_comment IS '自由記入欄';


--
-- Name: COLUMN pro_bowlers.titles_count; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.titles_count IS 'タイトル保有数（pro_bowler_titles 件数キャッシュ）';


--
-- Name: COLUMN pro_bowlers.member_class; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.member_class IS 'player / pro_instructor / honorary_or_overseas / other';


--
-- Name: COLUMN pro_bowlers.can_enter_official_tournament; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_bowlers.can_enter_official_tournament IS '公式戦出場可否';


--
-- Name: pro_bowlers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_bowlers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_bowlers_id_seq OWNER TO postgres;

--
-- Name: pro_bowlers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_bowlers_id_seq OWNED BY public.pro_bowlers.id;


--
-- Name: pro_dsp; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_dsp (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    gender character varying(255),
    license_no character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    pro_bowler_id bigint
);


ALTER TABLE public.pro_dsp OWNER TO postgres;

--
-- Name: pro_dsp_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_dsp_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_dsp_id_seq OWNER TO postgres;

--
-- Name: pro_dsp_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_dsp_id_seq OWNED BY public.pro_dsp.id;


--
-- Name: pro_group; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_group (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_group OWNER TO postgres;

--
-- Name: COLUMN pro_group.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_group.name IS 'プログループ名（例：城南地区）';


--
-- Name: pro_group_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_group_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_group_id_seq OWNER TO postgres;

--
-- Name: pro_group_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_group_id_seq OWNED BY public.pro_group.id;


--
-- Name: pro_test; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    sex_id bigint NOT NULL,
    area_id bigint NOT NULL,
    license_id bigint NOT NULL,
    place_id bigint NOT NULL,
    record_type_id bigint NOT NULL,
    kaiin_status_id bigint NOT NULL,
    test_category_id bigint NOT NULL,
    test_venue_id bigint NOT NULL,
    test_result_status_id bigint NOT NULL,
    remarks text,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_test OWNER TO postgres;

--
-- Name: COLUMN pro_test.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test.name IS '受験者氏名';


--
-- Name: COLUMN pro_test.remarks; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test.remarks IS '備考';


--
-- Name: pro_test_attachment; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test_attachment (
    id bigint NOT NULL,
    pro_test_id bigint NOT NULL,
    file_path character varying(255) NOT NULL,
    file_type character varying(255),
    original_file_name character varying(255),
    mime_type character varying(255),
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_test_attachment OWNER TO postgres;

--
-- Name: COLUMN pro_test_attachment.file_path; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_attachment.file_path IS '保存パス';


--
-- Name: COLUMN pro_test_attachment.file_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_attachment.file_type IS 'ファイル種別（顔写真など）';


--
-- Name: pro_test_attachment_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_attachment_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_attachment_id_seq OWNER TO postgres;

--
-- Name: pro_test_attachment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_attachment_id_seq OWNED BY public.pro_test_attachment.id;


--
-- Name: pro_test_category; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test_category (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_test_category OWNER TO postgres;

--
-- Name: COLUMN pro_test_category.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_category.name IS 'テスト種別名（例：一次テスト）';


--
-- Name: pro_test_category_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_category_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_category_id_seq OWNER TO postgres;

--
-- Name: pro_test_category_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_category_id_seq OWNED BY public.pro_test_category.id;


--
-- Name: pro_test_comment; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test_comment (
    id bigint NOT NULL,
    pro_test_id bigint NOT NULL,
    comment text NOT NULL,
    posted_by character varying(255),
    posted_at timestamp(0) without time zone,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_test_comment OWNER TO postgres;

--
-- Name: COLUMN pro_test_comment.comment; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_comment.comment IS '自由記述のコメント・メモ';


--
-- Name: COLUMN pro_test_comment.posted_by; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_comment.posted_by IS '投稿者名または管理者ID';


--
-- Name: pro_test_comment_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_comment_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_comment_id_seq OWNER TO postgres;

--
-- Name: pro_test_comment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_comment_id_seq OWNED BY public.pro_test_comment.id;


--
-- Name: pro_test_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_id_seq OWNER TO postgres;

--
-- Name: pro_test_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_id_seq OWNED BY public.pro_test.id;


--
-- Name: pro_test_result_status; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test_result_status (
    id bigint NOT NULL,
    status character varying(255) NOT NULL,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_test_result_status OWNER TO postgres;

--
-- Name: COLUMN pro_test_result_status.status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_result_status.status IS '合否区分（合格、不合格、辞退など）';


--
-- Name: pro_test_result_status_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_result_status_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_result_status_id_seq OWNER TO postgres;

--
-- Name: pro_test_result_status_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_result_status_id_seq OWNED BY public.pro_test_result_status.id;


--
-- Name: pro_test_schedule; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test_schedule (
    id bigint NOT NULL,
    year integer NOT NULL,
    schedule_name character varying(255) NOT NULL,
    start_date date,
    end_date date,
    application_start date,
    application_end date,
    venue_id bigint,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_test_schedule OWNER TO postgres;

--
-- Name: COLUMN pro_test_schedule.year; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_schedule.year IS '開催年';


--
-- Name: COLUMN pro_test_schedule.schedule_name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_schedule.schedule_name IS 'スケジュール名（第○回など）';


--
-- Name: pro_test_schedule_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_schedule_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_schedule_id_seq OWNER TO postgres;

--
-- Name: pro_test_schedule_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_schedule_id_seq OWNED BY public.pro_test_schedule.id;


--
-- Name: pro_test_score; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test_score (
    id bigint NOT NULL,
    pro_test_id bigint NOT NULL,
    game_no integer NOT NULL,
    score integer NOT NULL,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_test_score OWNER TO postgres;

--
-- Name: COLUMN pro_test_score.game_no; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_score.game_no IS '第何ゲームか';


--
-- Name: COLUMN pro_test_score.score; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_score.score IS 'スコア';


--
-- Name: pro_test_score_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_score_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_score_id_seq OWNER TO postgres;

--
-- Name: pro_test_score_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_score_id_seq OWNED BY public.pro_test_score.id;


--
-- Name: pro_test_score_summary; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test_score_summary (
    id bigint NOT NULL,
    pro_test_id bigint NOT NULL,
    total_score integer,
    average_score numeric(5,2),
    passed_flag boolean DEFAULT false NOT NULL,
    remarks text,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_test_score_summary OWNER TO postgres;

--
-- Name: COLUMN pro_test_score_summary.total_score; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_score_summary.total_score IS '合計スコア';


--
-- Name: COLUMN pro_test_score_summary.average_score; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_score_summary.average_score IS 'アベレージ';


--
-- Name: COLUMN pro_test_score_summary.passed_flag; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_score_summary.passed_flag IS '通過フラグ';


--
-- Name: COLUMN pro_test_score_summary.remarks; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_score_summary.remarks IS '備考';


--
-- Name: pro_test_score_summary_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_score_summary_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_score_summary_id_seq OWNER TO postgres;

--
-- Name: pro_test_score_summary_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_score_summary_id_seq OWNED BY public.pro_test_score_summary.id;


--
-- Name: pro_test_status_log; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test_status_log (
    id bigint NOT NULL,
    pro_test_id bigint NOT NULL,
    status_code character varying(255) NOT NULL,
    memo text,
    changed_at timestamp(0) without time zone,
    updated_by character varying(255)
);


ALTER TABLE public.pro_test_status_log OWNER TO postgres;

--
-- Name: COLUMN pro_test_status_log.status_code; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_status_log.status_code IS 'ステータス（例：書類審査通過）';


--
-- Name: COLUMN pro_test_status_log.memo; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_status_log.memo IS '補足メモ';


--
-- Name: pro_test_status_log_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_status_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_status_log_id_seq OWNER TO postgres;

--
-- Name: pro_test_status_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_status_log_id_seq OWNED BY public.pro_test_status_log.id;


--
-- Name: pro_test_venue; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pro_test_venue (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(255),
    phone character varying(255),
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.pro_test_venue OWNER TO postgres;

--
-- Name: COLUMN pro_test_venue.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_venue.name IS '会場名（ボウリング場名など）';


--
-- Name: COLUMN pro_test_venue.address; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_venue.address IS '住所';


--
-- Name: COLUMN pro_test_venue.phone; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pro_test_venue.phone IS '電話番号';


--
-- Name: pro_test_venue_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pro_test_venue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pro_test_venue_id_seq OWNER TO postgres;

--
-- Name: pro_test_venue_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pro_test_venue_id_seq OWNED BY public.pro_test_venue.id;


--
-- Name: record_types; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.record_types (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    record_type character varying(255) NOT NULL,
    tournament_name character varying(255) NOT NULL,
    game_numbers character varying(255) NOT NULL,
    frame_number character varying(255),
    awarded_on date NOT NULL,
    certification_number character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT record_types_record_type_check CHECK (((record_type)::text = ANY ((ARRAY['perfect'::character varying, 'seven_ten'::character varying, 'eight_hundred'::character varying])::text[])))
);


ALTER TABLE public.record_types OWNER TO postgres;

--
-- Name: record_types_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.record_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.record_types_id_seq OWNER TO postgres;

--
-- Name: record_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.record_types_id_seq OWNED BY public.record_types.id;


--
-- Name: registered_balls; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.registered_balls (
    id bigint NOT NULL,
    license_no character varying(255) NOT NULL,
    approved_ball_id bigint NOT NULL,
    serial_number character varying(255) NOT NULL,
    registered_at date NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    expires_at date,
    inspection_number character varying(255),
    pro_bowler_id bigint
);


ALTER TABLE public.registered_balls OWNER TO postgres;

--
-- Name: COLUMN registered_balls.expires_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.registered_balls.expires_at IS '有効期限';


--
-- Name: registered_balls_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.registered_balls_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.registered_balls_id_seq OWNER TO postgres;

--
-- Name: registered_balls_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.registered_balls_id_seq OWNED BY public.registered_balls.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO postgres;

--
-- Name: sexes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sexes (
    id bigint NOT NULL,
    label text NOT NULL,
    update_date timestamp(0) without time zone,
    created_by character varying(255),
    updated_by character varying(255)
);


ALTER TABLE public.sexes OWNER TO postgres;

--
-- Name: COLUMN sexes.label; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sexes.label IS '性別名（例：男性、女性）';


--
-- Name: sexes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sexes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sexes_id_seq OWNER TO postgres;

--
-- Name: sexes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sexes_id_seq OWNED BY public.sexes.id;


--
-- Name: sponsors; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sponsors (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    logo_path character varying(255),
    website character varying(255),
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.sponsors OWNER TO postgres;

--
-- Name: sponsors_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sponsors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sponsors_id_seq OWNER TO postgres;

--
-- Name: sponsors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sponsors_id_seq OWNED BY public.sponsors.id;


--
-- Name: stage_settings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.stage_settings (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    stage character varying(255) NOT NULL,
    total_games integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    enabled boolean DEFAULT true NOT NULL
);


ALTER TABLE public.stage_settings OWNER TO postgres;

--
-- Name: stage_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.stage_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.stage_settings_id_seq OWNER TO postgres;

--
-- Name: stage_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.stage_settings_id_seq OWNED BY public.stage_settings.id;


--
-- Name: tournament_auto_draw_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_auto_draw_logs (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    target_type character varying(10) NOT NULL,
    deadline_at timestamp(0) without time zone,
    executed_at timestamp(0) without time zone NOT NULL,
    total_pending integer DEFAULT 0 NOT NULL,
    success_count integer DEFAULT 0 NOT NULL,
    failed_count integer DEFAULT 0 NOT NULL,
    details_json json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tournament_auto_draw_logs OWNER TO postgres;

--
-- Name: tournament_auto_draw_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournament_auto_draw_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournament_auto_draw_logs_id_seq OWNER TO postgres;

--
-- Name: tournament_auto_draw_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournament_auto_draw_logs_id_seq OWNED BY public.tournament_auto_draw_logs.id;


--
-- Name: tournament_awards; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_awards (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    rank integer NOT NULL,
    prize_money integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tournament_awards OWNER TO postgres;

--
-- Name: tournament_awards_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournament_awards_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournament_awards_id_seq OWNER TO postgres;

--
-- Name: tournament_awards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournament_awards_id_seq OWNED BY public.tournament_awards.id;


--
-- Name: tournament_draw_reminder_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_draw_reminder_logs (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    tournament_entry_id bigint NOT NULL,
    reminder_kind character varying(20) NOT NULL,
    pending_type character varying(10) NOT NULL,
    scheduled_for_date date,
    dispatch_key character varying(255),
    recipient_email character varying(255) NOT NULL,
    subject character varying(200) NOT NULL,
    status character varying(20) DEFAULT 'sent'::character varying NOT NULL,
    sent_at timestamp(0) without time zone,
    error_message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tournament_draw_reminder_logs OWNER TO postgres;

--
-- Name: tournament_draw_reminder_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournament_draw_reminder_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournament_draw_reminder_logs_id_seq OWNER TO postgres;

--
-- Name: tournament_draw_reminder_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournament_draw_reminder_logs_id_seq OWNED BY public.tournament_draw_reminder_logs.id;


--
-- Name: tournament_entries; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_entries (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    status character varying(255) DEFAULT 'entry'::character varying NOT NULL,
    is_paid boolean DEFAULT false NOT NULL,
    shift_drawn boolean DEFAULT false NOT NULL,
    lane_drawn boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    shift character varying(20),
    lane smallint,
    checked_in_at timestamp(0) without time zone,
    waitlist_priority integer,
    waitlisted_at timestamp(0) without time zone,
    waitlist_note text,
    promoted_from_waitlist_at timestamp(0) without time zone,
    preferred_shift_code character varying(20)
);


ALTER TABLE public.tournament_entries OWNER TO postgres;

--
-- Name: tournament_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournament_entries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournament_entries_id_seq OWNER TO postgres;

--
-- Name: tournament_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournament_entries_id_seq OWNED BY public.tournament_entries.id;


--
-- Name: tournament_entry_balls; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_entry_balls (
    id bigint NOT NULL,
    tournament_entry_id bigint,
    used_ball_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tournament_entry_balls OWNER TO postgres;

--
-- Name: tournament_entry_balls_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournament_entry_balls_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournament_entry_balls_id_seq OWNER TO postgres;

--
-- Name: tournament_entry_balls_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournament_entry_balls_id_seq OWNED BY public.tournament_entry_balls.id;


--
-- Name: tournament_files; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_files (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    type character varying(32) NOT NULL,
    title character varying(255),
    file_path character varying(255) NOT NULL,
    visibility character varying(16) DEFAULT 'public'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.tournament_files OWNER TO postgres;

--
-- Name: tournament_files_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournament_files_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournament_files_id_seq OWNER TO postgres;

--
-- Name: tournament_files_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournament_files_id_seq OWNED BY public.tournament_files.id;


--
-- Name: tournament_organizations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_organizations (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    category character varying(32) NOT NULL,
    name character varying(255) NOT NULL,
    url character varying(255),
    sort_order integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.tournament_organizations OWNER TO postgres;

--
-- Name: tournament_organizations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournament_organizations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournament_organizations_id_seq OWNER TO postgres;

--
-- Name: tournament_organizations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournament_organizations_id_seq OWNED BY public.tournament_organizations.id;


--
-- Name: tournament_participants; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_participants (
    id bigint NOT NULL,
    tournament_id bigint NOT NULL,
    pro_bowler_license_no character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    pro_bowler_id bigint
);


ALTER TABLE public.tournament_participants OWNER TO postgres;

--
-- Name: tournament_participants_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournament_participants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournament_participants_id_seq OWNER TO postgres;

--
-- Name: tournament_participants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournament_participants_id_seq OWNED BY public.tournament_participants.id;


--
-- Name: tournament_points; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_points (
    tournament_id bigint NOT NULL,
    rank integer NOT NULL,
    point integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tournament_points OWNER TO postgres;

--
-- Name: tournament_results; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournament_results (
    id bigint NOT NULL,
    pro_bowler_license_no character varying(255) NOT NULL,
    tournament_id bigint NOT NULL,
    ranking integer,
    points integer,
    total_pin integer,
    games integer,
    average numeric(5,2),
    prize_money integer,
    ranking_year integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    amateur_name character varying(255),
    pro_bowler_id bigint
);


ALTER TABLE public.tournament_results OWNER TO postgres;

--
-- Name: tournament_results_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournament_results_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournament_results_id_seq OWNER TO postgres;

--
-- Name: tournament_results_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournament_results_id_seq OWNED BY public.tournament_results.id;


--
-- Name: tournaments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournaments (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    start_date date,
    end_date date,
    venue_name character varying(255),
    venue_address character varying(255),
    venue_tel character varying(255),
    venue_fax character varying(255),
    host character varying(255),
    special_sponsor character varying(255),
    support character varying(255),
    sponsor character varying(255),
    supervisor character varying(255),
    authorized_by character varying(255),
    broadcast character varying(255),
    streaming character varying(255),
    prize character varying(255),
    audience character varying(255),
    entry_conditions text,
    materials text,
    previous_event character varying(255),
    image_path character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    shift_draw_open_at timestamp(0) without time zone,
    shift_draw_close_at timestamp(0) without time zone,
    lane_draw_open_at timestamp(0) without time zone,
    lane_draw_close_at timestamp(0) without time zone,
    lane_from smallint,
    lane_to smallint,
    shift_codes character varying(50),
    title_logo_path character varying(255),
    year integer,
    gender character varying(255) DEFAULT 'X'::character varying NOT NULL,
    official_type character varying(255) DEFAULT 'official'::character varying NOT NULL,
    entry_start timestamp(0) without time zone,
    entry_end timestamp(0) without time zone,
    inspection_required boolean DEFAULT false NOT NULL,
    title_category character varying(32) DEFAULT 'normal'::character varying NOT NULL,
    venue_id bigint,
    broadcast_url character varying(255),
    streaming_url character varying(255),
    previous_event_url character varying(255),
    spectator_policy character varying(16),
    admission_fee text,
    hero_image_path character varying(255),
    poster_images json,
    extra_venues json,
    sidebar_schedule json,
    award_highlights json,
    gallery_items json,
    simple_result_pdfs json,
    result_cards json,
    use_shift_draw boolean DEFAULT false NOT NULL,
    use_lane_draw boolean DEFAULT false NOT NULL,
    lane_assignment_mode character varying(30) DEFAULT 'single_lane'::character varying NOT NULL,
    box_player_count smallint,
    odd_lane_player_count smallint,
    even_lane_player_count smallint,
    accept_shift_preference boolean DEFAULT false NOT NULL,
    auto_draw_reminder_enabled boolean DEFAULT false NOT NULL,
    auto_draw_reminder_days_before smallint DEFAULT '7'::smallint NOT NULL,
    auto_draw_reminder_pending_type character varying(10) DEFAULT 'either'::character varying NOT NULL,
    shift_auto_draw_reminder_enabled boolean DEFAULT false NOT NULL,
    shift_auto_draw_reminder_send_on date,
    lane_auto_draw_reminder_enabled boolean DEFAULT false NOT NULL,
    lane_auto_draw_reminder_send_on date,
    CONSTRAINT tournaments_gender_check CHECK (((gender)::text = ANY ((ARRAY['M'::character varying, 'F'::character varying, 'X'::character varying])::text[]))),
    CONSTRAINT tournaments_official_type_check CHECK (((official_type)::text = ANY ((ARRAY['official'::character varying, 'approved'::character varying, 'other'::character varying])::text[])))
);


ALTER TABLE public.tournaments OWNER TO postgres;

--
-- Name: COLUMN tournaments.name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.name IS '大会名';


--
-- Name: COLUMN tournaments.venue_name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.venue_name IS 'ボウリング場名';


--
-- Name: COLUMN tournaments.venue_address; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.venue_address IS '住所';


--
-- Name: COLUMN tournaments.venue_tel; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.venue_tel IS '電話番号';


--
-- Name: COLUMN tournaments.venue_fax; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.venue_fax IS 'FAX番号';


--
-- Name: COLUMN tournaments.host; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.host IS '主催';


--
-- Name: COLUMN tournaments.special_sponsor; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.special_sponsor IS '特別協賛';


--
-- Name: COLUMN tournaments.support; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.support IS '後援';


--
-- Name: COLUMN tournaments.sponsor; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.sponsor IS '協賛';


--
-- Name: COLUMN tournaments.supervisor; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.supervisor IS '主管';


--
-- Name: COLUMN tournaments.authorized_by; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.authorized_by IS '公認';


--
-- Name: COLUMN tournaments.broadcast; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.broadcast IS '放送';


--
-- Name: COLUMN tournaments.streaming; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.streaming IS '配信';


--
-- Name: COLUMN tournaments.prize; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.prize IS '賞金';


--
-- Name: COLUMN tournaments.audience; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.audience IS '観戦';


--
-- Name: COLUMN tournaments.entry_conditions; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.entry_conditions IS '出場条件';


--
-- Name: COLUMN tournaments.materials; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.materials IS '資料';


--
-- Name: COLUMN tournaments.previous_event; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.previous_event IS '前年大会';


--
-- Name: COLUMN tournaments.image_path; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.image_path IS '大会画像パス';


--
-- Name: COLUMN tournaments.year; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.year IS '開催年度';


--
-- Name: COLUMN tournaments.gender; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.gender IS 'M=男子, F=女子, X=混合/未設定';


--
-- Name: COLUMN tournaments.official_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tournaments.official_type IS '大会区分';


--
-- Name: tournaments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournaments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournaments_id_seq OWNER TO postgres;

--
-- Name: tournaments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournaments_id_seq OWNED BY public.tournaments.id;


--
-- Name: tournamentscore; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tournamentscore (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tournamentscore OWNER TO postgres;

--
-- Name: tournamentscore_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tournamentscore_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tournamentscore_id_seq OWNER TO postgres;

--
-- Name: tournamentscore_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tournamentscore_id_seq OWNED BY public.tournamentscore.id;


--
-- Name: trainings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.trainings (
    id bigint NOT NULL,
    code character varying(50) NOT NULL,
    name character varying(255) NOT NULL,
    valid_for_months integer DEFAULT 12 NOT NULL,
    mandatory boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.trainings OWNER TO postgres;

--
-- Name: trainings_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.trainings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.trainings_id_seq OWNER TO postgres;

--
-- Name: trainings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.trainings_id_seq OWNED BY public.trainings.id;


--
-- Name: used_balls; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.used_balls (
    id bigint NOT NULL,
    pro_bowler_id bigint NOT NULL,
    approved_ball_id bigint NOT NULL,
    serial_number character varying(255) NOT NULL,
    inspection_number character varying(255),
    registered_at date NOT NULL,
    expires_at date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.used_balls OWNER TO postgres;

--
-- Name: used_balls_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.used_balls_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.used_balls_id_seq OWNER TO postgres;

--
-- Name: used_balls_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.used_balls_id_seq OWNED BY public.used_balls.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    role character varying(255) DEFAULT 'bowler'::character varying NOT NULL,
    is_admin boolean DEFAULT false NOT NULL,
    pro_bowler_license_no character varying(255),
    pro_bowler_id bigint,
    license_no character varying(255)
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: COLUMN users.role; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.users.role IS 'admin or bowler';


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: venues; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.venues (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(255),
    postal_code character varying(255),
    city character varying(255),
    prefecture character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tel character varying(50),
    fax character varying(50),
    website_url character varying(255),
    note text
);


ALTER TABLE public.venues OWNER TO postgres;

--
-- Name: venues_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.venues_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.venues_id_seq OWNER TO postgres;

--
-- Name: venues_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.venues_id_seq OWNED BY public.venues.id;


--
-- Name: annual_dues id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.annual_dues ALTER COLUMN id SET DEFAULT nextval('public.annual_dues_id_seq'::regclass);


--
-- Name: approved_ball_pro_bowler id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approved_ball_pro_bowler ALTER COLUMN id SET DEFAULT nextval('public.approved_ball_pro_bowler_id_seq'::regclass);


--
-- Name: approved_balls id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approved_balls ALTER COLUMN id SET DEFAULT nextval('public.approved_balls_id_seq'::regclass);


--
-- Name: area id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.area ALTER COLUMN id SET DEFAULT nextval('public.area_id_seq'::regclass);


--
-- Name: ball_info id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ball_info ALTER COLUMN id SET DEFAULT nextval('public.ball_info_id_seq'::regclass);


--
-- Name: calendar_events id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calendar_events ALTER COLUMN id SET DEFAULT nextval('public.calendar_events_id_seq'::regclass);


--
-- Name: distribution_patterns id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.distribution_patterns ALTER COLUMN id SET DEFAULT nextval('public.distribution_patterns_id_seq'::regclass);


--
-- Name: districts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.districts ALTER COLUMN id SET DEFAULT nextval('public.districts_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: flash_news id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.flash_news ALTER COLUMN id SET DEFAULT nextval('public.flash_news_id_seq'::regclass);


--
-- Name: game_scores id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.game_scores ALTER COLUMN id SET DEFAULT nextval('public.game_scores_id_seq'::regclass);


--
-- Name: group_mail_recipients id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_mail_recipients ALTER COLUMN id SET DEFAULT nextval('public.group_mail_recipients_id_seq'::regclass);


--
-- Name: group_mailouts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_mailouts ALTER COLUMN id SET DEFAULT nextval('public.group_mailouts_id_seq'::regclass);


--
-- Name: group_members id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members ALTER COLUMN id SET DEFAULT nextval('public.group_members_id_seq'::regclass);


--
-- Name: groups id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.groups ALTER COLUMN id SET DEFAULT nextval('public.groups_id_seq'::regclass);


--
-- Name: hof_inductions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.hof_inductions ALTER COLUMN id SET DEFAULT nextval('public.hof_inductions_id_seq'::regclass);


--
-- Name: hof_photos id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.hof_photos ALTER COLUMN id SET DEFAULT nextval('public.hof_photos_id_seq'::regclass);


--
-- Name: information_files id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.information_files ALTER COLUMN id SET DEFAULT nextval('public.information_files_id_seq'::regclass);


--
-- Name: informations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.informations ALTER COLUMN id SET DEFAULT nextval('public.informations_id_seq'::regclass);


--
-- Name: instructor_registry id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instructor_registry ALTER COLUMN id SET DEFAULT nextval('public.instructor_registry_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: kaiin_status id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.kaiin_status ALTER COLUMN id SET DEFAULT nextval('public.kaiin_status_id_seq'::regclass);


--
-- Name: license id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.license ALTER COLUMN id SET DEFAULT nextval('public.license_id_seq'::regclass);


--
-- Name: match_videos id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.match_videos ALTER COLUMN id SET DEFAULT nextval('public.match_videos_id_seq'::regclass);


--
-- Name: media_publications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.media_publications ALTER COLUMN id SET DEFAULT nextval('public.media_publications_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: organization_masters id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_masters ALTER COLUMN id SET DEFAULT nextval('public.organization_masters_id_seq'::regclass);


--
-- Name: place id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.place ALTER COLUMN id SET DEFAULT nextval('public.place_id_seq'::regclass);


--
-- Name: point_distributions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.point_distributions ALTER COLUMN id SET DEFAULT nextval('public.point_distributions_id_seq'::regclass);


--
-- Name: prize_distributions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.prize_distributions ALTER COLUMN id SET DEFAULT nextval('public.prize_distributions_id_seq'::regclass);


--
-- Name: pro_bowler_biographies id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_biographies ALTER COLUMN id SET DEFAULT nextval('public.pro_bowler_biographies_id_seq'::regclass);


--
-- Name: pro_bowler_instructor_info id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_instructor_info ALTER COLUMN id SET DEFAULT nextval('public.pro_bowler_instructor_info_id_seq'::regclass);


--
-- Name: pro_bowler_links id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_links ALTER COLUMN id SET DEFAULT nextval('public.pro_bowler_links_id_seq'::regclass);


--
-- Name: pro_bowler_profiles id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_profiles ALTER COLUMN id SET DEFAULT nextval('public.pro_bowler_profiles_id_seq'::regclass);


--
-- Name: pro_bowler_sponsors id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_sponsors ALTER COLUMN id SET DEFAULT nextval('public.pro_bowler_sponsors_id_seq'::regclass);


--
-- Name: pro_bowler_titles id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_titles ALTER COLUMN id SET DEFAULT nextval('public.pro_bowler_titles_id_seq'::regclass);


--
-- Name: pro_bowler_trainings id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_trainings ALTER COLUMN id SET DEFAULT nextval('public.pro_bowler_trainings_id_seq'::regclass);


--
-- Name: pro_bowlers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowlers ALTER COLUMN id SET DEFAULT nextval('public.pro_bowlers_id_seq'::regclass);


--
-- Name: pro_dsp id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_dsp ALTER COLUMN id SET DEFAULT nextval('public.pro_dsp_id_seq'::regclass);


--
-- Name: pro_group id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_group ALTER COLUMN id SET DEFAULT nextval('public.pro_group_id_seq'::regclass);


--
-- Name: pro_test id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test ALTER COLUMN id SET DEFAULT nextval('public.pro_test_id_seq'::regclass);


--
-- Name: pro_test_attachment id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_attachment ALTER COLUMN id SET DEFAULT nextval('public.pro_test_attachment_id_seq'::regclass);


--
-- Name: pro_test_category id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_category ALTER COLUMN id SET DEFAULT nextval('public.pro_test_category_id_seq'::regclass);


--
-- Name: pro_test_comment id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_comment ALTER COLUMN id SET DEFAULT nextval('public.pro_test_comment_id_seq'::regclass);


--
-- Name: pro_test_result_status id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_result_status ALTER COLUMN id SET DEFAULT nextval('public.pro_test_result_status_id_seq'::regclass);


--
-- Name: pro_test_schedule id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_schedule ALTER COLUMN id SET DEFAULT nextval('public.pro_test_schedule_id_seq'::regclass);


--
-- Name: pro_test_score id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_score ALTER COLUMN id SET DEFAULT nextval('public.pro_test_score_id_seq'::regclass);


--
-- Name: pro_test_score_summary id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_score_summary ALTER COLUMN id SET DEFAULT nextval('public.pro_test_score_summary_id_seq'::regclass);


--
-- Name: pro_test_status_log id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_status_log ALTER COLUMN id SET DEFAULT nextval('public.pro_test_status_log_id_seq'::regclass);


--
-- Name: pro_test_venue id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_venue ALTER COLUMN id SET DEFAULT nextval('public.pro_test_venue_id_seq'::regclass);


--
-- Name: record_types id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.record_types ALTER COLUMN id SET DEFAULT nextval('public.record_types_id_seq'::regclass);


--
-- Name: registered_balls id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.registered_balls ALTER COLUMN id SET DEFAULT nextval('public.registered_balls_id_seq'::regclass);


--
-- Name: sexes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sexes ALTER COLUMN id SET DEFAULT nextval('public.sexes_id_seq'::regclass);


--
-- Name: sponsors id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sponsors ALTER COLUMN id SET DEFAULT nextval('public.sponsors_id_seq'::regclass);


--
-- Name: stage_settings id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stage_settings ALTER COLUMN id SET DEFAULT nextval('public.stage_settings_id_seq'::regclass);


--
-- Name: tournament_auto_draw_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_auto_draw_logs ALTER COLUMN id SET DEFAULT nextval('public.tournament_auto_draw_logs_id_seq'::regclass);


--
-- Name: tournament_awards id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_awards ALTER COLUMN id SET DEFAULT nextval('public.tournament_awards_id_seq'::regclass);


--
-- Name: tournament_draw_reminder_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_draw_reminder_logs ALTER COLUMN id SET DEFAULT nextval('public.tournament_draw_reminder_logs_id_seq'::regclass);


--
-- Name: tournament_entries id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entries ALTER COLUMN id SET DEFAULT nextval('public.tournament_entries_id_seq'::regclass);


--
-- Name: tournament_entry_balls id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entry_balls ALTER COLUMN id SET DEFAULT nextval('public.tournament_entry_balls_id_seq'::regclass);


--
-- Name: tournament_files id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_files ALTER COLUMN id SET DEFAULT nextval('public.tournament_files_id_seq'::regclass);


--
-- Name: tournament_organizations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_organizations ALTER COLUMN id SET DEFAULT nextval('public.tournament_organizations_id_seq'::regclass);


--
-- Name: tournament_participants id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_participants ALTER COLUMN id SET DEFAULT nextval('public.tournament_participants_id_seq'::regclass);


--
-- Name: tournament_results id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_results ALTER COLUMN id SET DEFAULT nextval('public.tournament_results_id_seq'::regclass);


--
-- Name: tournaments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournaments ALTER COLUMN id SET DEFAULT nextval('public.tournaments_id_seq'::regclass);


--
-- Name: tournamentscore id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournamentscore ALTER COLUMN id SET DEFAULT nextval('public.tournamentscore_id_seq'::regclass);


--
-- Name: trainings id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trainings ALTER COLUMN id SET DEFAULT nextval('public.trainings_id_seq'::regclass);


--
-- Name: used_balls id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.used_balls ALTER COLUMN id SET DEFAULT nextval('public.used_balls_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: venues id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.venues ALTER COLUMN id SET DEFAULT nextval('public.venues_id_seq'::regclass);


--
-- Name: annual_dues annual_dues_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.annual_dues
    ADD CONSTRAINT annual_dues_pkey PRIMARY KEY (id);


--
-- Name: annual_dues annual_dues_pro_bowler_id_year_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.annual_dues
    ADD CONSTRAINT annual_dues_pro_bowler_id_year_unique UNIQUE (pro_bowler_id, year);


--
-- Name: approved_ball_pro_bowler approved_ball_pro_bowler_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approved_ball_pro_bowler
    ADD CONSTRAINT approved_ball_pro_bowler_pkey PRIMARY KEY (id);


--
-- Name: approved_balls approved_balls_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approved_balls
    ADD CONSTRAINT approved_balls_pkey PRIMARY KEY (id);


--
-- Name: area area_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.area
    ADD CONSTRAINT area_pkey PRIMARY KEY (id);


--
-- Name: ball_info ball_info_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ball_info
    ADD CONSTRAINT ball_info_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: calendar_days calendar_days_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calendar_days
    ADD CONSTRAINT calendar_days_pkey PRIMARY KEY (date);


--
-- Name: calendar_events calendar_events_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calendar_events
    ADD CONSTRAINT calendar_events_pkey PRIMARY KEY (id);


--
-- Name: calendar_events calendar_events_title_start_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.calendar_events
    ADD CONSTRAINT calendar_events_title_start_unique UNIQUE (title, start_date);


--
-- Name: distribution_patterns distribution_patterns_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.distribution_patterns
    ADD CONSTRAINT distribution_patterns_pkey PRIMARY KEY (id);


--
-- Name: districts districts_name_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.districts
    ADD CONSTRAINT districts_name_unique UNIQUE (name);


--
-- Name: districts districts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.districts
    ADD CONSTRAINT districts_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: flash_news flash_news_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.flash_news
    ADD CONSTRAINT flash_news_pkey PRIMARY KEY (id);


--
-- Name: game_scores game_scores_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.game_scores
    ADD CONSTRAINT game_scores_pkey PRIMARY KEY (id);


--
-- Name: group_mail_recipients group_mail_recipients_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_mail_recipients
    ADD CONSTRAINT group_mail_recipients_pkey PRIMARY KEY (id);


--
-- Name: group_mailouts group_mailouts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_mailouts
    ADD CONSTRAINT group_mailouts_pkey PRIMARY KEY (id);


--
-- Name: group_members group_members_group_id_pro_bowler_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_group_id_pro_bowler_id_unique UNIQUE (group_id, pro_bowler_id);


--
-- Name: group_members group_members_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_pkey PRIMARY KEY (id);


--
-- Name: groups groups_key_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_key_unique UNIQUE (key);


--
-- Name: groups groups_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (id);


--
-- Name: hof_inductions hof_inductions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.hof_inductions
    ADD CONSTRAINT hof_inductions_pkey PRIMARY KEY (id);


--
-- Name: hof_inductions hof_inductions_pro_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.hof_inductions
    ADD CONSTRAINT hof_inductions_pro_id_unique UNIQUE (pro_id);


--
-- Name: hof_photos hof_photos_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.hof_photos
    ADD CONSTRAINT hof_photos_pkey PRIMARY KEY (id);


--
-- Name: information_files information_files_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.information_files
    ADD CONSTRAINT information_files_pkey PRIMARY KEY (id);


--
-- Name: informations informations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.informations
    ADD CONSTRAINT informations_pkey PRIMARY KEY (id);


--
-- Name: instructor_registry instructor_registry_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instructor_registry
    ADD CONSTRAINT instructor_registry_pkey PRIMARY KEY (id);


--
-- Name: instructor_registry instructor_registry_source_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instructor_registry
    ADD CONSTRAINT instructor_registry_source_unique UNIQUE (source_type, source_key);


--
-- Name: instructors instructors_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instructors
    ADD CONSTRAINT instructors_pkey PRIMARY KEY (license_no);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: kaiin_status kaiin_status_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.kaiin_status
    ADD CONSTRAINT kaiin_status_pkey PRIMARY KEY (id);


--
-- Name: license license_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.license
    ADD CONSTRAINT license_pkey PRIMARY KEY (id);


--
-- Name: match_videos match_videos_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.match_videos
    ADD CONSTRAINT match_videos_pkey PRIMARY KEY (id);


--
-- Name: media_publications media_publications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.media_publications
    ADD CONSTRAINT media_publications_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: organization_masters organization_masters_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.organization_masters
    ADD CONSTRAINT organization_masters_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: place place_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.place
    ADD CONSTRAINT place_pkey PRIMARY KEY (id);


--
-- Name: point_distributions point_distributions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.point_distributions
    ADD CONSTRAINT point_distributions_pkey PRIMARY KEY (id);


--
-- Name: prize_distributions prize_distributions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.prize_distributions
    ADD CONSTRAINT prize_distributions_pkey PRIMARY KEY (id);


--
-- Name: approved_ball_pro_bowler pro_ball_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approved_ball_pro_bowler
    ADD CONSTRAINT pro_ball_unique UNIQUE (pro_bowler_license_no, approved_ball_id, year);


--
-- Name: pro_bowler_biographies pro_bowler_biographies_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_biographies
    ADD CONSTRAINT pro_bowler_biographies_pkey PRIMARY KEY (id);


--
-- Name: pro_bowler_instructor_info pro_bowler_instructor_info_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_instructor_info
    ADD CONSTRAINT pro_bowler_instructor_info_pkey PRIMARY KEY (id);


--
-- Name: pro_bowler_links pro_bowler_links_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_links
    ADD CONSTRAINT pro_bowler_links_pkey PRIMARY KEY (id);


--
-- Name: pro_bowler_profiles pro_bowler_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_profiles
    ADD CONSTRAINT pro_bowler_profiles_pkey PRIMARY KEY (id);


--
-- Name: pro_bowler_sponsors pro_bowler_sponsors_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_sponsors
    ADD CONSTRAINT pro_bowler_sponsors_pkey PRIMARY KEY (id);


--
-- Name: pro_bowler_titles pro_bowler_titles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_titles
    ADD CONSTRAINT pro_bowler_titles_pkey PRIMARY KEY (id);


--
-- Name: pro_bowler_titles pro_bowler_titles_pro_bowler_id_tournament_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_titles
    ADD CONSTRAINT pro_bowler_titles_pro_bowler_id_tournament_id_unique UNIQUE (pro_bowler_id, tournament_id);


--
-- Name: pro_bowler_trainings pro_bowler_trainings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowler_trainings
    ADD CONSTRAINT pro_bowler_trainings_pkey PRIMARY KEY (id);


--
-- Name: pro_bowlers pro_bowlers_license_no_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowlers
    ADD CONSTRAINT pro_bowlers_license_no_unique UNIQUE (license_no);


--
-- Name: pro_bowlers pro_bowlers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowlers
    ADD CONSTRAINT pro_bowlers_pkey PRIMARY KEY (id);


--
-- Name: pro_dsp pro_dsp_license_no_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_dsp
    ADD CONSTRAINT pro_dsp_license_no_unique UNIQUE (license_no);


--
-- Name: pro_dsp pro_dsp_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_dsp
    ADD CONSTRAINT pro_dsp_pkey PRIMARY KEY (id);


--
-- Name: pro_group pro_group_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_group
    ADD CONSTRAINT pro_group_pkey PRIMARY KEY (id);


--
-- Name: pro_test_attachment pro_test_attachment_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_attachment
    ADD CONSTRAINT pro_test_attachment_pkey PRIMARY KEY (id);


--
-- Name: pro_test_category pro_test_category_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_category
    ADD CONSTRAINT pro_test_category_pkey PRIMARY KEY (id);


--
-- Name: pro_test_comment pro_test_comment_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_comment
    ADD CONSTRAINT pro_test_comment_pkey PRIMARY KEY (id);


--
-- Name: pro_test pro_test_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test
    ADD CONSTRAINT pro_test_pkey PRIMARY KEY (id);


--
-- Name: pro_test_result_status pro_test_result_status_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_result_status
    ADD CONSTRAINT pro_test_result_status_pkey PRIMARY KEY (id);


--
-- Name: pro_test_schedule pro_test_schedule_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_schedule
    ADD CONSTRAINT pro_test_schedule_pkey PRIMARY KEY (id);


--
-- Name: pro_test_score pro_test_score_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_score
    ADD CONSTRAINT pro_test_score_pkey PRIMARY KEY (id);


--
-- Name: pro_test_score_summary pro_test_score_summary_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_score_summary
    ADD CONSTRAINT pro_test_score_summary_pkey PRIMARY KEY (id);


--
-- Name: pro_test_status_log pro_test_status_log_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_status_log
    ADD CONSTRAINT pro_test_status_log_pkey PRIMARY KEY (id);


--
-- Name: pro_test_venue pro_test_venue_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_test_venue
    ADD CONSTRAINT pro_test_venue_pkey PRIMARY KEY (id);


--
-- Name: record_types record_types_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.record_types
    ADD CONSTRAINT record_types_pkey PRIMARY KEY (id);


--
-- Name: registered_balls registered_balls_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.registered_balls
    ADD CONSTRAINT registered_balls_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: sexes sexes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sexes
    ADD CONSTRAINT sexes_pkey PRIMARY KEY (id);


--
-- Name: sponsors sponsors_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sponsors
    ADD CONSTRAINT sponsors_pkey PRIMARY KEY (id);


--
-- Name: stage_settings stage_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stage_settings
    ADD CONSTRAINT stage_settings_pkey PRIMARY KEY (id);


--
-- Name: stage_settings stage_settings_tournament_id_stage_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stage_settings
    ADD CONSTRAINT stage_settings_tournament_id_stage_unique UNIQUE (tournament_id, stage);


--
-- Name: tournament_entries t_entries_unique_bowler_per_tournament; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entries
    ADD CONSTRAINT t_entries_unique_bowler_per_tournament UNIQUE (tournament_id, pro_bowler_id);


--
-- Name: tournament_entry_balls t_entry_balls_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entry_balls
    ADD CONSTRAINT t_entry_balls_unique UNIQUE (tournament_entry_id, used_ball_id);


--
-- Name: tournament_entry_balls teb_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entry_balls
    ADD CONSTRAINT teb_unique UNIQUE (tournament_entry_id, used_ball_id);


--
-- Name: tournament_auto_draw_logs tournament_auto_draw_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_auto_draw_logs
    ADD CONSTRAINT tournament_auto_draw_logs_pkey PRIMARY KEY (id);


--
-- Name: tournament_awards tournament_awards_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_awards
    ADD CONSTRAINT tournament_awards_pkey PRIMARY KEY (id);


--
-- Name: tournament_draw_reminder_logs tournament_draw_reminder_logs_dispatch_key_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_draw_reminder_logs
    ADD CONSTRAINT tournament_draw_reminder_logs_dispatch_key_unique UNIQUE (dispatch_key);


--
-- Name: tournament_draw_reminder_logs tournament_draw_reminder_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_draw_reminder_logs
    ADD CONSTRAINT tournament_draw_reminder_logs_pkey PRIMARY KEY (id);


--
-- Name: tournament_entries tournament_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entries
    ADD CONSTRAINT tournament_entries_pkey PRIMARY KEY (id);


--
-- Name: tournament_entry_balls tournament_entry_balls_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entry_balls
    ADD CONSTRAINT tournament_entry_balls_pkey PRIMARY KEY (id);


--
-- Name: tournament_files tournament_files_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_files
    ADD CONSTRAINT tournament_files_pkey PRIMARY KEY (id);


--
-- Name: tournament_organizations tournament_organizations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_organizations
    ADD CONSTRAINT tournament_organizations_pkey PRIMARY KEY (id);


--
-- Name: tournament_participants tournament_participants_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_participants
    ADD CONSTRAINT tournament_participants_pkey PRIMARY KEY (id);


--
-- Name: tournament_points tournament_points_rank_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_points
    ADD CONSTRAINT tournament_points_rank_unique UNIQUE (rank);


--
-- Name: tournament_results tournament_results_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_results
    ADD CONSTRAINT tournament_results_pkey PRIMARY KEY (id);


--
-- Name: tournaments tournaments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournaments
    ADD CONSTRAINT tournaments_pkey PRIMARY KEY (id);


--
-- Name: tournamentscore tournamentscore_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournamentscore
    ADD CONSTRAINT tournamentscore_pkey PRIMARY KEY (id);


--
-- Name: trainings trainings_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trainings
    ADD CONSTRAINT trainings_code_unique UNIQUE (code);


--
-- Name: trainings trainings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trainings
    ADD CONSTRAINT trainings_pkey PRIMARY KEY (id);


--
-- Name: used_balls used_balls_inspection_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.used_balls
    ADD CONSTRAINT used_balls_inspection_number_unique UNIQUE (inspection_number);


--
-- Name: used_balls used_balls_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.used_balls
    ADD CONSTRAINT used_balls_pkey PRIMARY KEY (id);


--
-- Name: used_balls used_balls_serial_number_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.used_balls
    ADD CONSTRAINT used_balls_serial_number_unique UNIQUE (serial_number);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_license_no_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_license_no_unique UNIQUE (license_no);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_pro_bowler_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pro_bowler_id_unique UNIQUE (pro_bowler_id);


--
-- Name: venues venues_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.venues
    ADD CONSTRAINT venues_pkey PRIMARY KEY (id);


--
-- Name: approved_ball_pro_bowler_pro_bowler_id_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX approved_ball_pro_bowler_pro_bowler_id_idx ON public.approved_ball_pro_bowler USING btree (pro_bowler_id);


--
-- Name: game_scores_pro_bowler_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX game_scores_pro_bowler_id_index ON public.game_scores USING btree (pro_bowler_id);


--
-- Name: group_mail_recipients_mailout_id_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX group_mail_recipients_mailout_id_status_index ON public.group_mail_recipients USING btree (mailout_id, status);


--
-- Name: group_mail_recipients_pro_bowler_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX group_mail_recipients_pro_bowler_id_index ON public.group_mail_recipients USING btree (pro_bowler_id);


--
-- Name: hof_inductions_year_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX hof_inductions_year_index ON public.hof_inductions USING btree (year);


--
-- Name: hof_photos_hof_id_sort_order_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX hof_photos_hof_id_sort_order_index ON public.hof_photos USING btree (hof_id, sort_order);


--
-- Name: idx_pro_bowlers_license_no_num; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pro_bowlers_license_no_num ON public.pro_bowlers USING btree (license_no_num);


--
-- Name: idx_pro_bowlers_sex; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pro_bowlers_sex ON public.pro_bowlers USING btree (sex);


--
-- Name: information_files_information_id_sort_order_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX information_files_information_id_sort_order_index ON public.information_files USING btree (information_id, sort_order);


--
-- Name: information_files_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX information_files_type_index ON public.information_files USING btree (type);


--
-- Name: information_files_visibility_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX information_files_visibility_index ON public.information_files USING btree (visibility);


--
-- Name: informations_category_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX informations_category_index ON public.informations USING btree (category);


--
-- Name: informations_is_public_audience_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX informations_is_public_audience_index ON public.informations USING btree (is_public, audience);


--
-- Name: informations_published_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX informations_published_at_index ON public.informations USING btree (published_at);


--
-- Name: informations_required_training_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX informations_required_training_id_index ON public.informations USING btree (required_training_id);


--
-- Name: informations_starts_at_ends_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX informations_starts_at_ends_at_index ON public.informations USING btree (starts_at, ends_at);


--
-- Name: instructor_registry_active_visible_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_active_visible_idx ON public.instructor_registry USING btree (is_active, is_visible);


--
-- Name: instructor_registry_category_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_category_idx ON public.instructor_registry USING btree (instructor_category);


--
-- Name: instructor_registry_cert_no_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_cert_no_idx ON public.instructor_registry USING btree (cert_no);


--
-- Name: instructor_registry_current_category_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_current_category_idx ON public.instructor_registry USING btree (is_current, instructor_category);


--
-- Name: instructor_registry_current_cert_category_unique; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX instructor_registry_current_cert_category_unique ON public.instructor_registry USING btree (cert_no, instructor_category) WHERE ((is_current = true) AND (cert_no IS NOT NULL));


--
-- Name: instructor_registry_current_license_category_unique; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX instructor_registry_current_license_category_unique ON public.instructor_registry USING btree (license_no, instructor_category) WHERE ((is_current = true) AND (license_no IS NOT NULL));


--
-- Name: instructor_registry_current_pro_bowler_category_unique; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX instructor_registry_current_pro_bowler_category_unique ON public.instructor_registry USING btree (pro_bowler_id, instructor_category) WHERE ((is_current = true) AND (pro_bowler_id IS NOT NULL));


--
-- Name: instructor_registry_district_id_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_district_id_idx ON public.instructor_registry USING btree (district_id);


--
-- Name: instructor_registry_grade_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_grade_idx ON public.instructor_registry USING btree (grade);


--
-- Name: instructor_registry_legacy_license_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_legacy_license_idx ON public.instructor_registry USING btree (legacy_instructor_license_no);


--
-- Name: instructor_registry_license_no_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_license_no_idx ON public.instructor_registry USING btree (license_no);


--
-- Name: instructor_registry_pro_bowler_id_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_pro_bowler_id_idx ON public.instructor_registry USING btree (pro_bowler_id);


--
-- Name: instructor_registry_renewal_due_on_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_renewal_due_on_idx ON public.instructor_registry USING btree (renewal_due_on);


--
-- Name: instructor_registry_renewal_year_status_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_renewal_year_status_idx ON public.instructor_registry USING btree (renewal_year, renewal_status);


--
-- Name: instructor_registry_source_registered_at_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructor_registry_source_registered_at_idx ON public.instructor_registry USING btree (source_registered_at);


--
-- Name: instructors_pro_bowler_id_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX instructors_pro_bowler_id_idx ON public.instructors USING btree (pro_bowler_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: kaiin_status_name_unique; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX kaiin_status_name_unique ON public.kaiin_status USING btree (name);


--
-- Name: pro_bowler_titles_pro_bowler_id_tournament_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX pro_bowler_titles_pro_bowler_id_tournament_id_index ON public.pro_bowler_titles USING btree (pro_bowler_id, tournament_id);


--
-- Name: pro_bowler_titles_tournament_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX pro_bowler_titles_tournament_id_index ON public.pro_bowler_titles USING btree (tournament_id);


--
-- Name: pro_bowler_trainings_pro_bowler_id_training_id_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX pro_bowler_trainings_pro_bowler_id_training_id_expires_at_index ON public.pro_bowler_trainings USING btree (pro_bowler_id, training_id, expires_at);


--
-- Name: pro_bowlers_can_enter_official_tournament_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX pro_bowlers_can_enter_official_tournament_idx ON public.pro_bowlers USING btree (can_enter_official_tournament);


--
-- Name: pro_bowlers_district_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX pro_bowlers_district_id_index ON public.pro_bowlers USING btree (district_id);


--
-- Name: pro_bowlers_member_class_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX pro_bowlers_member_class_idx ON public.pro_bowlers USING btree (member_class);


--
-- Name: pro_bowlers_titles_count_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX pro_bowlers_titles_count_index ON public.pro_bowlers USING btree (titles_count);


--
-- Name: pro_dsp_pro_bowler_id_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX pro_dsp_pro_bowler_id_idx ON public.pro_dsp USING btree (pro_bowler_id);


--
-- Name: registered_balls_pro_bowler_id_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX registered_balls_pro_bowler_id_idx ON public.registered_balls USING btree (pro_bowler_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: t_entries_bowler_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX t_entries_bowler_idx ON public.tournament_entries USING btree (pro_bowler_id);


--
-- Name: t_entries_tournament_status_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX t_entries_tournament_status_idx ON public.tournament_entries USING btree (tournament_id, status);


--
-- Name: t_entries_waitlist_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX t_entries_waitlist_idx ON public.tournament_entries USING btree (tournament_id, status, waitlist_priority);


--
-- Name: tadl_tournament_target_executed_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX tadl_tournament_target_executed_idx ON public.tournament_auto_draw_logs USING btree (tournament_id, target_type, executed_at);


--
-- Name: tdrl_scheduled_date_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX tdrl_scheduled_date_idx ON public.tournament_draw_reminder_logs USING btree (scheduled_for_date);


--
-- Name: tdrl_tournament_pending_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX tdrl_tournament_pending_idx ON public.tournament_draw_reminder_logs USING btree (tournament_id, pending_type);


--
-- Name: tournament_participants_pro_bowler_id_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX tournament_participants_pro_bowler_id_idx ON public.tournament_participants USING btree (pro_bowler_id);


--
-- Name: tournament_results_pro_bowler_id_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX tournament_results_pro_bowler_id_idx ON public.tournament_results USING btree (pro_bowler_id);


--
-- Name: users_pro_bowler_id_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_pro_bowler_id_idx ON public.users USING btree (pro_bowler_id);


--
-- Name: users_pro_bowler_license_no_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX users_pro_bowler_license_no_index ON public.users USING btree (pro_bowler_license_no);


--
-- Name: annual_dues annual_dues_pro_bowler_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.annual_dues
    ADD CONSTRAINT annual_dues_pro_bowler_id_foreign FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE CASCADE;


--
-- Name: approved_ball_pro_bowler approved_ball_pro_bowler_pro_bowler_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.approved_ball_pro_bowler
    ADD CONSTRAINT approved_ball_pro_bowler_pro_bowler_id_fk FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE SET NULL;


--
-- Name: hof_photos fk_hof_photos_hof; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.hof_photos
    ADD CONSTRAINT fk_hof_photos_hof FOREIGN KEY (hof_id) REFERENCES public.hof_inductions(id) ON DELETE CASCADE;


--
-- Name: game_scores game_scores_pro_bowler_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.game_scores
    ADD CONSTRAINT game_scores_pro_bowler_id_foreign FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE SET NULL;


--
-- Name: game_scores game_scores_tournament_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.game_scores
    ADD CONSTRAINT game_scores_tournament_id_foreign FOREIGN KEY (tournament_id) REFERENCES public.tournaments(id) ON DELETE CASCADE;


--
-- Name: group_mail_recipients group_mail_recipients_mailout_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_mail_recipients
    ADD CONSTRAINT group_mail_recipients_mailout_id_foreign FOREIGN KEY (mailout_id) REFERENCES public.group_mailouts(id) ON DELETE CASCADE;


--
-- Name: group_mail_recipients group_mail_recipients_pro_bowler_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_mail_recipients
    ADD CONSTRAINT group_mail_recipients_pro_bowler_id_foreign FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id);


--
-- Name: group_mailouts group_mailouts_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_mailouts
    ADD CONSTRAINT group_mailouts_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: group_mailouts group_mailouts_sender_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_mailouts
    ADD CONSTRAINT group_mailouts_sender_user_id_foreign FOREIGN KEY (sender_user_id) REFERENCES public.users(id);


--
-- Name: group_members group_members_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_group_id_foreign FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- Name: group_members group_members_pro_bowler_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_pro_bowler_id_foreign FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE CASCADE;


--
-- Name: information_files information_files_information_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.information_files
    ADD CONSTRAINT information_files_information_id_foreign FOREIGN KEY (information_id) REFERENCES public.informations(id) ON DELETE CASCADE;


--
-- Name: informations informations_required_training_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.informations
    ADD CONSTRAINT informations_required_training_id_foreign FOREIGN KEY (required_training_id) REFERENCES public.trainings(id) ON DELETE SET NULL;


--
-- Name: instructor_registry instructor_registry_district_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instructor_registry
    ADD CONSTRAINT instructor_registry_district_id_foreign FOREIGN KEY (district_id) REFERENCES public.districts(id) ON DELETE SET NULL;


--
-- Name: instructor_registry instructor_registry_pro_bowler_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instructor_registry
    ADD CONSTRAINT instructor_registry_pro_bowler_id_foreign FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE SET NULL;


--
-- Name: instructors instructors_pro_bowler_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.instructors
    ADD CONSTRAINT instructors_pro_bowler_id_fk FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE SET NULL;


--
-- Name: pro_bowlers pro_bowlers_district_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowlers
    ADD CONSTRAINT pro_bowlers_district_id_foreign FOREIGN KEY (district_id) REFERENCES public.districts(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: pro_bowlers pro_bowlers_membership_type_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowlers
    ADD CONSTRAINT pro_bowlers_membership_type_foreign FOREIGN KEY (membership_type) REFERENCES public.kaiin_status(name) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: pro_bowlers pro_bowlers_sex_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_bowlers
    ADD CONSTRAINT pro_bowlers_sex_foreign FOREIGN KEY (sex) REFERENCES public.sexes(id) ON UPDATE RESTRICT ON DELETE RESTRICT;


--
-- Name: pro_dsp pro_dsp_pro_bowler_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pro_dsp
    ADD CONSTRAINT pro_dsp_pro_bowler_id_fk FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE SET NULL;


--
-- Name: stage_settings stage_settings_tournament_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stage_settings
    ADD CONSTRAINT stage_settings_tournament_id_foreign FOREIGN KEY (tournament_id) REFERENCES public.tournaments(id) ON DELETE CASCADE;


--
-- Name: tournament_auto_draw_logs tournament_auto_draw_logs_tournament_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_auto_draw_logs
    ADD CONSTRAINT tournament_auto_draw_logs_tournament_id_foreign FOREIGN KEY (tournament_id) REFERENCES public.tournaments(id) ON DELETE CASCADE;


--
-- Name: tournament_draw_reminder_logs tournament_draw_reminder_logs_tournament_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_draw_reminder_logs
    ADD CONSTRAINT tournament_draw_reminder_logs_tournament_entry_id_foreign FOREIGN KEY (tournament_entry_id) REFERENCES public.tournament_entries(id) ON DELETE CASCADE;


--
-- Name: tournament_draw_reminder_logs tournament_draw_reminder_logs_tournament_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_draw_reminder_logs
    ADD CONSTRAINT tournament_draw_reminder_logs_tournament_id_foreign FOREIGN KEY (tournament_id) REFERENCES public.tournaments(id) ON DELETE CASCADE;


--
-- Name: tournament_entries tournament_entries_pro_bowler_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entries
    ADD CONSTRAINT tournament_entries_pro_bowler_id_foreign FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE CASCADE;


--
-- Name: tournament_entries tournament_entries_tournament_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entries
    ADD CONSTRAINT tournament_entries_tournament_id_foreign FOREIGN KEY (tournament_id) REFERENCES public.tournaments(id) ON DELETE CASCADE;


--
-- Name: tournament_entry_balls tournament_entry_balls_tournament_entry_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entry_balls
    ADD CONSTRAINT tournament_entry_balls_tournament_entry_id_foreign FOREIGN KEY (tournament_entry_id) REFERENCES public.tournament_entries(id) ON DELETE CASCADE;


--
-- Name: tournament_entry_balls tournament_entry_balls_used_ball_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_entry_balls
    ADD CONSTRAINT tournament_entry_balls_used_ball_id_foreign FOREIGN KEY (used_ball_id) REFERENCES public.used_balls(id) ON DELETE CASCADE;


--
-- Name: tournament_files tournament_files_tournament_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_files
    ADD CONSTRAINT tournament_files_tournament_id_foreign FOREIGN KEY (tournament_id) REFERENCES public.tournaments(id) ON DELETE CASCADE;


--
-- Name: tournament_organizations tournament_organizations_tournament_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_organizations
    ADD CONSTRAINT tournament_organizations_tournament_id_foreign FOREIGN KEY (tournament_id) REFERENCES public.tournaments(id) ON DELETE CASCADE;


--
-- Name: tournament_participants tournament_participants_pro_bowler_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_participants
    ADD CONSTRAINT tournament_participants_pro_bowler_id_foreign FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE SET NULL;


--
-- Name: tournament_results tournament_results_pro_bowler_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournament_results
    ADD CONSTRAINT tournament_results_pro_bowler_id_foreign FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE SET NULL;


--
-- Name: tournaments tournaments_venue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tournaments
    ADD CONSTRAINT tournaments_venue_id_foreign FOREIGN KEY (venue_id) REFERENCES public.venues(id) ON DELETE SET NULL;


--
-- Name: users users_pro_bowler_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pro_bowler_id_foreign FOREIGN KEY (pro_bowler_id) REFERENCES public.pro_bowlers(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict 9dYDtdLD6BQvaxUZ9lVJM23nldifk869IQwAQanWuxNCOB6qGaWY7IbI9vLVuXx

