// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared Bootstrap-icon typeahead with a live preview.
 *
 * Options come from local_moderncommerce_icon_options() in lib.php, which supplies a
 * label, domain and keyword blob per icon so the search matches on more than the raw
 * class name.
 *
 * @module     local_moderncommerce/icon_picker
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useId, useMemo, useState} from "react";

export type IconOption = {value: string; label: string; domain?: string; keywords?: string};

/** Strip any leading "bi-"/"bi " so stored values and typed values compare equal. */
export const normIcon = (v: string): string => String(v ?? "").trim().replace(/^bi\s+/, "").replace(/^bi-/, "");

/** Full Bootstrap icon class for an icon value, with a safe fallback. */
export const iconCls = (v: string, fallback = "check-circle"): string => `bi bi-${normIcon(v) || fallback}`;

type IconPickerProps = {
    value: string;
    label: string;
    options: IconOption[];
    onChange: (v: string) => void;
    /** Icon used when the field is emptied or nothing matches. */
    fallback?: string;
    /** Optional help text rendered under the control. */
    help?: string;
    emptyLabel?: string;
    disabled?: boolean;
};

/**
 * Searchable icon field: type to filter by name, label, domain or keyword; the current
 * value is always previewed beside the input.
 *
 * @param props Icon picker props.
 * @returns The icon picker element.
 */
export function IconPicker(
    {value, label, options, onChange, fallback = "check-circle", help = "", emptyLabel = "No icons found",
        disabled = false}: IconPickerProps
) {
    const inputId = useId();
    const norm = normIcon(value);
    const [query, setQuery] = useState(norm);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        setQuery(norm);
    }, [norm]);

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const iconNeedle = normIcon(query).toLowerCase();
        const matches = needle === "" ? options : options.filter((o) => {
            const hay = [o.label, normIcon(o.value), o.domain ?? "", o.keywords ?? ""].join(" ").toLowerCase();
            return hay.includes(needle) || hay.includes(iconNeedle);
        });
        const selected = options.find((o) => normIcon(o.value) === norm);
        const ordered = selected && !matches.includes(selected) ? [selected, ...matches] : matches;
        return ordered.slice(0, 50);
    }, [norm, options, query]);

    const commit = () => {
        const raw = query.trim();
        const nq = normIcon(raw);
        const exact = options.find((o) =>
            normIcon(o.value).toLowerCase() === nq.toLowerCase() || o.label.toLowerCase() === raw.toLowerCase());
        const next = normIcon(exact?.value ?? filtered[0]?.value ?? nq) || fallback;
        onChange(next);
        setQuery(next);
    };

    const pick = (o: IconOption) => {
        const next = normIcon(o.value) || fallback;
        onChange(next);
        setQuery(next);
        setOpen(false);
    };

    return (
        <div className="mc-sf-field mc-sf-iconpick">
            <label htmlFor={inputId}>{label}</label>
            <div className="mc-sf-iconpick__control">
                <span className="mc-sf-iconpick__preview" aria-hidden="true"><i className={iconCls(norm, fallback)} /></span>
                <input
                    aria-autocomplete="list"
                    aria-expanded={open ? "true" : "false"}
                    aria-label={label}
                    autoComplete="off"
                    className="mc-sf-iconpick__input"
                    disabled={disabled}
                    id={inputId}
                    onBlur={() => window.setTimeout(() => {
                        commit();
                        setOpen(false);
                    }, 150)}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    placeholder={fallback}
                    role="combobox"
                    type="search"
                    value={query}
                />
            </div>
            {help !== "" && <span className="mc-field-help">{help}</span>}
            {open && (
                <div className="mc-sf-iconpick__menu" role="listbox">
                    {filtered.length === 0 && <div className="mc-sf-iconpick__empty">{emptyLabel}</div>}
                    {filtered.map((o) => {
                        const oi = normIcon(o.value);
                        return (
                            <button
                                className="mc-button mc-sf-iconpick__item"
                                data-mc-button="ghost"
                                key={oi}
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    pick(o);
                                }}
                                role="option"
                                type="button"
                            >
                                <i className={iconCls(oi, fallback)} aria-hidden="true" />
                                <span className="mc-sf-iconpick__name">{o.label}</span>
                                <code>{oi}</code>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export default IconPicker;
