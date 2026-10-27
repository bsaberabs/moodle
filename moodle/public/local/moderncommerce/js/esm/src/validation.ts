// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Framework-free form validation rules for Modern Commerce.
 *
 * Every rule is a plain function returning the error message when the value is
 * invalid, or undefined when it is fine. Messages are always supplied by the
 * caller from its own `labels` prop — this module never contains user-facing
 * English, so it stays translatable.
 *
 * @module     local_moderncommerce/validation
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export type Rule<TValues = Record<string, unknown>> =
    (value: unknown, values: TValues) => string | undefined;

export type RuleMap<TValues = Record<string, unknown>> =
    Partial<Record<Extract<keyof TValues, string>, Rule<TValues> | Array<Rule<TValues>>>>;

export type FieldErrorMap = Record<string, string>;

/** Key used for errors that belong to the form as a whole rather than a field. */
export const FORM_ERROR_KEY = "_form";

/**
 * Substitutes a single {$a} placeholder, matching Moodle's get_string convention
 * so the same lang string works on both sides of the wire.
 */
export const fmt = (template: string, a: string | number): string =>
    String(template).replace(/\{\$a\}/g, String(a));

export const isBlank = (value: unknown): boolean => {
    if (value === null || typeof value === "undefined") {
        return true;
    }
    if (typeof value === "string") {
        return value.trim() === "";
    }
    if (Array.isArray(value)) {
        return value.length === 0;
    }
    return false;
};

const asText = (value: unknown): string => (typeof value === "string" ? value.trim() : String(value ?? "").trim());

export const EMAIL_RE = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
export const HEX_RE = /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/;
/**
 * Colours a storefront widget accepts: a hex value, or a Modern Commerce design token.
 * Mirrors preset_service::colour() in PHP, which sanitises widget and preset colours to
 * the same two shapes -- widget defaults and presets ship tokens like var(--mc-secondary),
 * so a hex-only rule would reject the plugin's own seeded values.
 */
export const WIDGET_COLOUR_RE = /^(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|var\(--mc-[a-z0-9-]+\))$/;
export const SLUG_RE = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

type EmptyOption = {allowEmpty?: boolean};

/** Skip a format rule when the value is blank — emptiness is `required`'s job. */
const skipBlank = (value: unknown, options?: EmptyOption): boolean =>
    isBlank(value) && (options?.allowEmpty ?? true);

export const required = <T = Record<string, unknown>>(message: string): Rule<T> =>
    (value) => (isBlank(value) ? message : undefined);

/** For id-bearing selects and pickers where 0 means "nothing chosen". */
export const requiredId = <T = Record<string, unknown>>(message: string): Rule<T> =>
    (value) => (Number(value) > 0 ? undefined : message);

/** For selects whose first option is a non-value placeholder. */
export const requiredChoice = <T = Record<string, unknown>>(message: string, placeholder = ""): Rule<T> =>
    (value) => (isBlank(value) || String(value) === placeholder ? message : undefined);

export const email = <T = Record<string, unknown>>(message: string, options?: EmptyOption): Rule<T> =>
    (value) => (skipBlank(value, options) ? undefined : (EMAIL_RE.test(asText(value)) ? undefined : message));

/**
 * Comma, semicolon or newline separated address list. The message may carry a
 * {$a} placeholder, which is filled with the offending addresses.
 */
export const emailList = <T = Record<string, unknown>>(message: string, options?: EmptyOption): Rule<T> =>
    (value) => {
        if (skipBlank(value, options)) {
            return undefined;
        }
        const parts = asText(value).split(/[,;\n]/).map((part) => part.trim()).filter(Boolean);
        if (parts.length === 0) {
            return message;
        }
        const bad = parts.filter((part) => !EMAIL_RE.test(part));
        return bad.length ? fmt(message, bad.join(", ")) : undefined;
    };

export const url = <T = Record<string, unknown>>(
    message: string,
    options?: EmptyOption & {requireHttp?: boolean},
): Rule<T> =>
    (value) => {
        if (skipBlank(value, options)) {
            return undefined;
        }
        const text = asText(value);
        try {
            const parsed = new URL(text);
            if (options?.requireHttp && parsed.protocol !== "http:" && parsed.protocol !== "https:") {
                return message;
            }
            return undefined;
        } catch {
            return message;
        }
    };

export const hexColour = <T = Record<string, unknown>>(message: string, options?: EmptyOption): Rule<T> =>
    (value) => (skipBlank(value, options) ? undefined : (HEX_RE.test(asText(value)) ? undefined : message));

export const widgetColour = <T = Record<string, unknown>>(message: string, options?: EmptyOption): Rule<T> =>
    (value) => (skipBlank(value, options)
        ? undefined
        : (WIDGET_COLOUR_RE.test(asText(value)) ? undefined : message));

export const slug = <T = Record<string, unknown>>(message: string, options?: EmptyOption): Rule<T> =>
    (value) => (skipBlank(value, options) ? undefined : (SLUG_RE.test(asText(value)) ? undefined : message));

export const pattern = <T = Record<string, unknown>>(
    expression: RegExp,
    message: string,
    options?: EmptyOption,
): Rule<T> =>
    (value) => (skipBlank(value, options) ? undefined : (expression.test(asText(value)) ? undefined : message));

export const number = <T = Record<string, unknown>>(message: string, options?: EmptyOption): Rule<T> =>
    (value) => {
        if (skipBlank(value, options)) {
            return undefined;
        }
        return Number.isFinite(Number(asText(value))) ? undefined : message;
    };

export const integer = <T = Record<string, unknown>>(message: string, options?: EmptyOption): Rule<T> =>
    (value) => {
        if (skipBlank(value, options)) {
            return undefined;
        }
        const parsed = Number(asText(value));
        return Number.isInteger(parsed) ? undefined : message;
    };

export const min = <T = Record<string, unknown>>(bound: number, message: string): Rule<T> =>
    (value) => {
        if (isBlank(value)) {
            return undefined;
        }
        const parsed = Number(asText(value));
        return Number.isFinite(parsed) && parsed < bound ? fmt(message, bound) : undefined;
    };

export const max = <T = Record<string, unknown>>(bound: number, message: string): Rule<T> =>
    (value) => {
        if (isBlank(value)) {
            return undefined;
        }
        const parsed = Number(asText(value));
        return Number.isFinite(parsed) && parsed > bound ? fmt(message, bound) : undefined;
    };

/** Percentage between 0 and 100 inclusive. */
export const percent = <T = Record<string, unknown>>(message: string): Rule<T> =>
    (value) => {
        if (isBlank(value)) {
            return undefined;
        }
        const parsed = Number(asText(value));
        if (!Number.isFinite(parsed)) {
            return message;
        }
        return parsed < 0 || parsed > 100 ? message : undefined;
    };

export const minLength = <T = Record<string, unknown>>(length: number, message: string): Rule<T> =>
    (value) => {
        if (isBlank(value)) {
            return undefined;
        }
        return asText(value).length < length ? fmt(message, length) : undefined;
    };

export const maxLength = <T = Record<string, unknown>>(length: number, message: string): Rule<T> =>
    (value) => {
        if (isBlank(value)) {
            return undefined;
        }
        return asText(value).length > length ? fmt(message, length) : undefined;
    };

/**
 * Normalises a date-ish value to a millisecond timestamp. Forms in this plugin
 * store dates as ISO strings in some places and unix seconds in others, so both
 * are accepted. Returns null when the value is unusable.
 */
export const stamp = (value: unknown): number | null => {
    if (isBlank(value)) {
        return null;
    }
    if (typeof value === "number" && Number.isFinite(value)) {
        return value < 1e11 ? value * 1000 : value;
    }
    const text = asText(value);
    if (/^\d{9,13}$/.test(text)) {
        const parsed = Number(text);
        return parsed < 1e11 ? parsed * 1000 : parsed;
    }
    const parsed = Date.parse(text);
    return Number.isNaN(parsed) ? null : parsed;
};

export const isoDate = <T = Record<string, unknown>>(message: string, options?: EmptyOption): Rule<T> =>
    (value) => (skipBlank(value, options) ? undefined : (stamp(value) === null ? message : undefined));

/*
 * Cross-field comparators.
 *
 * Always attach these to the LATER field of the pair, so the error appears on
 * the control the user is most likely to want to change. Each returns undefined
 * when either side is blank — reporting a missing value is `required`'s job, and
 * doing it here would double up the message.
 */

export const lteField = <T extends Record<string, unknown>>(
    other: Extract<keyof T, string>,
    message: string,
): Rule<T> =>
    (value, values) => {
        if (isBlank(value) || isBlank(values?.[other])) {
            return undefined;
        }
        const left = Number(asText(value));
        const right = Number(asText(values[other]));
        if (!Number.isFinite(left) || !Number.isFinite(right)) {
            return undefined;
        }
        return left > right ? message : undefined;
    };

export const gteField = <T extends Record<string, unknown>>(
    other: Extract<keyof T, string>,
    message: string,
): Rule<T> =>
    (value, values) => {
        if (isBlank(value) || isBlank(values?.[other])) {
            return undefined;
        }
        const left = Number(asText(value));
        const right = Number(asText(values[other]));
        if (!Number.isFinite(left) || !Number.isFinite(right)) {
            return undefined;
        }
        return left < right ? message : undefined;
    };

/** Attach to the end date: fails when it is not strictly after the start date. */
export const afterField = <T extends Record<string, unknown>>(
    start: Extract<keyof T, string>,
    message: string,
): Rule<T> =>
    (value, values) => {
        const end = stamp(value);
        const begin = stamp(values?.[start]);
        if (end === null || begin === null) {
            return undefined;
        }
        return end <= begin ? message : undefined;
    };

/** Only enforce the wrapped rules when the predicate holds. */
export const when = <T extends Record<string, unknown>>(
    predicate: (values: T) => boolean,
    ...rules: Array<Rule<T>>
): Rule<T> =>
    (value, values) => {
        if (!predicate(values)) {
            return undefined;
        }
        for (const rule of rules) {
            const message = rule(value, values);
            if (message) {
                return message;
            }
        }
        return undefined;
    };

/**
 * Runs a rule map against a value set. Rules for one field are evaluated in
 * order and the first failure wins, so cheap checks should be listed first.
 */
export const runRules = <T extends Record<string, unknown>>(values: T, rules: RuleMap<T>): FieldErrorMap => {
    const errors: FieldErrorMap = {};

    (Object.keys(rules) as Array<Extract<keyof T, string>>).forEach((field) => {
        const entry = rules[field];
        if (!entry) {
            return;
        }
        const list = Array.isArray(entry) ? entry : [entry];
        for (const rule of list) {
            const message = rule(values?.[field], values);
            if (message) {
                errors[field] = message;
                return;
            }
        }
    });

    return errors;
};

/**
 * Move focus to the first errored control, for surfaces that manage their own
 * error state rather than using the useFormValidation hook.
 *
 * `order` should list fields in the order they appear on screen — walking the
 * error map instead would land on whichever key happened to be inserted first.
 * Controls are found by `name`, then by the id patterns the admin screens use.
 */
export const focusFirstError = (
    errors: FieldErrorMap,
    order: string[],
    idPrefix = "",
): void => {
    const first = order.find((field) => errors[field])
        ?? Object.keys(errors).find((field) => field !== FORM_ERROR_KEY);
    if (!first) {
        return;
    }

    window.requestAnimationFrame(() => {
        const node = (document.getElementsByName(first)[0] as HTMLElement | undefined)
            ?? (idPrefix ? document.getElementById(`${idPrefix}${first}`) : null);
        if (!node) {
            return;
        }
        node.scrollIntoView({block: "center", behavior: "smooth"});
        node.focus({preventScroll: true});
    });
};

export type ServerFieldError = {field?: string; name?: string; message?: string};

/** Mix into a save response type to accept the per-field error contract. */
export type WithFieldErrors = {errors?: ServerFieldError[] | null};

export type SaveResponseLike = {
    success?: boolean;
    message?: string;
    errors?: ServerFieldError[] | Record<string, string> | null;
};

/**
 * Normalises the several shapes the web service layer returns into one map.
 *
 * The plugin has historically used both {field, message} and {name, message},
 * and some endpoints return a bare {field: message} object. Accepting all three
 * is what lets the PHP migration to a single key land independently of the
 * JavaScript that consumes it. Rows carrying neither key are kept under
 * FORM_ERROR_KEY so they surface in the summary banner instead of vanishing.
 */
export const normaliseServerErrors = (errors: SaveResponseLike["errors"]): FieldErrorMap => {
    const map: FieldErrorMap = {};
    if (!errors) {
        return map;
    }

    if (Array.isArray(errors)) {
        errors.forEach((entry) => {
            if (!entry) {
                return;
            }
            const message = String(entry.message ?? "").trim();
            if (message === "") {
                return;
            }
            const key = String(entry.field ?? entry.name ?? "").trim() || FORM_ERROR_KEY;
            if (!map[key]) {
                map[key] = message;
            }
        });
        return map;
    }

    Object.keys(errors).forEach((key) => {
        const message = String(errors[key] ?? "").trim();
        if (message !== "") {
            map[key] = message;
        }
    });

    return map;
};
