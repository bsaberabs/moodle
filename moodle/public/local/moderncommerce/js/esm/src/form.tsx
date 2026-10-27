// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Shared form primitives for Modern Commerce React surfaces.
 *
 * Wraps a control in the mc-* field vocabulary and wires the accessibility
 * contract in one place: the required asterisk stays aria-hidden while the
 * control carries aria-required, the inline message is associated through
 * aria-describedby, and aria-invalid is present only while an error is showing.
 *
 * Pair with useFormValidation to get validate-on-submit, revalidate-on-change
 * and focus-the-first-error behaviour without per-field wiring.
 *
 * @module     local_moderncommerce/form
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {createContext, useCallback, useContext, useId, useMemo, useRef, useState} from "react";
import type {
    FormEvent,
    InputHTMLAttributes,
    ReactNode,
    SelectHTMLAttributes,
    TextareaHTMLAttributes,
} from "react";
import {mcClasses} from "./design_system";
import {
    FORM_ERROR_KEY,
    normaliseServerErrors,
    runRules,
} from "./validation";
import type {FieldErrorMap, RuleMap, SaveResponseLike} from "./validation";

/* -------------------------------------------------------------------------- */
/* Field registry                                                             */
/* -------------------------------------------------------------------------- */

type FieldRegistry = {
    register: (field: string, node: HTMLElement | null) => void;
    lookup: (field: string) => HTMLElement | null;
};

const FormValidationContext = createContext<FieldRegistry | null>(null);

/**
 * Tracks the live DOM node for each field so the hook can focus the first
 * invalid control.
 *
 * The registry is created by useFormValidation and passed down to McForm rather
 * than the other way round: callers invoke the hook in their component body,
 * which sits above <McForm> in the tree, so a context read there would always
 * miss. Fields still consume it through context.
 */
export const useFieldRegistry = (): FieldRegistry => {
    const nodes = useRef(new Map<string, HTMLElement>());

    return useMemo(() => ({
        register: (field, node) => {
            if (node) {
                nodes.current.set(field, node);
            } else {
                nodes.current.delete(field);
            }
        },
        lookup: (field) => nodes.current.get(field) ?? null,
    }), []);
};

/* -------------------------------------------------------------------------- */
/* Field                                                                      */
/* -------------------------------------------------------------------------- */

export type FieldControlProps = {
    id: string;
    name: string;
    "aria-invalid": true | undefined;
    "aria-describedby": string | undefined;
    "aria-required": true | undefined;
    invalidClass: string;
    ref: (node: HTMLElement | null) => void;
};

export type FieldProps = {
    name: string;
    label: ReactNode;
    required?: boolean;
    help?: ReactNode;
    error?: string;
    className?: string;
    labelClassName?: string;
    children: (control: FieldControlProps) => ReactNode;
};

/**
 * Stacked label + control + help + error. The control is supplied through a
 * render prop so callers keep full control of the element while inheriting the
 * accessibility wiring.
 */
export function Field({
    name,
    label,
    required,
    help,
    error,
    className,
    labelClassName,
    children,
}: FieldProps) {
    const registry = useContext(FormValidationContext);
    const uid = useId();
    const id = `mc-f-${name}-${uid}`;
    const helpId = `${id}-help`;
    const errorId = `${id}-error`;

    const describedBy = [help ? helpId : null, error ? errorId : null]
        .filter(Boolean)
        .join(" ") || undefined;

    const control: FieldControlProps = {
        id,
        name,
        "aria-invalid": error ? true : undefined,
        "aria-describedby": describedBy,
        "aria-required": required ? true : undefined,
        invalidClass: error ? "mc-field-invalid" : "",
        ref: (node) => registry?.register(name, node),
    };

    return (
        <div className={mcClasses("mc-field", className)}>
            <label className={mcClasses("mc-field-label", labelClassName)} htmlFor={id}>
                {label}
                {required && <span className={mcClasses("mc-required")} aria-hidden="true">*</span>}
            </label>
            {children(control)}
            {help && <div className={mcClasses("mc-field-help")} id={helpId}>{help}</div>}
            {error && (
                <div className={mcClasses("mc-field-error")} id={errorId} role="alert">
                    <i className={mcClasses("bi bi-exclamation-circle mc-field-error__icon")} aria-hidden="true" />
                    <span>{error}</span>
                </div>
            )}
        </div>
    );
}

/* -------------------------------------------------------------------------- */
/* Controls                                                                   */
/* -------------------------------------------------------------------------- */

type CommonFieldProps = Pick<FieldProps, "name" | "label" | "required" | "help" | "error" | "className">;

export type McInputProps = CommonFieldProps
    & Omit<InputHTMLAttributes<HTMLInputElement>, "name" | "required" | "className" | "id">
    & {controlClassName?: string; onValueChange?: (value: string) => void};

export function McInput({
    name, label, required, help, error, className, controlClassName, onValueChange, onChange, ...rest
}: McInputProps) {
    return (
        <Field className={className} error={error} help={help} label={label} name={name} required={required}>
            {(control) => (
                <input
                    {...rest}
                    aria-describedby={control["aria-describedby"]}
                    aria-invalid={control["aria-invalid"]}
                    aria-required={control["aria-required"]}
                    className={mcClasses("mc-form-control", controlClassName, control.invalidClass)}
                    id={control.id}
                    name={control.name}
                    onChange={(event) => {
                        onValueChange?.(event.target.value);
                        onChange?.(event);
                    }}
                    ref={control.ref}
                    required={required}
                />
            )}
        </Field>
    );
}

export type McSelectOption = {value: string; label: string; disabled?: boolean};

export type McSelectProps = CommonFieldProps
    & Omit<SelectHTMLAttributes<HTMLSelectElement>, "name" | "required" | "className" | "id">
    & {
        options?: McSelectOption[];
        placeholder?: string;
        controlClassName?: string;
        onValueChange?: (value: string) => void;
    };

export function McSelect({
    name, label, required, help, error, className, controlClassName,
    options, placeholder, onValueChange, onChange, children, ...rest
}: McSelectProps) {
    return (
        <Field className={className} error={error} help={help} label={label} name={name} required={required}>
            {(control) => (
                <select
                    {...rest}
                    aria-describedby={control["aria-describedby"]}
                    aria-invalid={control["aria-invalid"]}
                    aria-required={control["aria-required"]}
                    className={mcClasses("mc-select", controlClassName, control.invalidClass)}
                    id={control.id}
                    name={control.name}
                    onChange={(event) => {
                        onValueChange?.(event.target.value);
                        onChange?.(event);
                    }}
                    ref={control.ref}
                    required={required}
                >
                    {placeholder !== undefined && <option value="">{placeholder}</option>}
                    {options
                        ? options.map((option) => (
                            <option disabled={option.disabled} key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))
                        : children}
                </select>
            )}
        </Field>
    );
}

export type McTextareaProps = CommonFieldProps
    & Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, "name" | "required" | "className" | "id">
    & {controlClassName?: string; onValueChange?: (value: string) => void};

export function McTextarea({
    name, label, required, help, error, className, controlClassName, onValueChange, onChange, ...rest
}: McTextareaProps) {
    return (
        <Field className={className} error={error} help={help} label={label} name={name} required={required}>
            {(control) => (
                <textarea
                    {...rest}
                    aria-describedby={control["aria-describedby"]}
                    aria-invalid={control["aria-invalid"]}
                    aria-required={control["aria-required"]}
                    className={mcClasses("mc-textarea", controlClassName, control.invalidClass)}
                    id={control.id}
                    name={control.name}
                    onChange={(event) => {
                        onValueChange?.(event.target.value);
                        onChange?.(event);
                    }}
                    ref={control.ref}
                    required={required}
                />
            )}
        </Field>
    );
}

export type McCheckboxProps = {
    name: string;
    label: ReactNode;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    help?: ReactNode;
    error?: string;
    className?: string;
    disabled?: boolean;
    variant?: "checkbox" | "switch";
};

/**
 * Checkboxes put their label beside the control rather than above it, so this
 * does not reuse Field. The error and help ids follow the same contract.
 */
export function McCheckbox({
    name, label, checked, onCheckedChange, help, error, className, disabled, variant = "checkbox",
}: McCheckboxProps) {
    const registry = useContext(FormValidationContext);
    const uid = useId();
    const id = `mc-f-${name}-${uid}`;
    const helpId = `${id}-help`;
    const errorId = `${id}-error`;
    const describedBy = [help ? helpId : null, error ? errorId : null].filter(Boolean).join(" ") || undefined;
    const wrapper = variant === "switch" ? "mc-switch" : "mc-checkbox";

    return (
        <div className={mcClasses("mc-field", className)}>
            <label className={mcClasses(wrapper, error ? "mc-field-invalid" : "")} htmlFor={id}>
                <input
                    aria-describedby={describedBy}
                    aria-invalid={error ? true : undefined}
                    checked={checked}
                    disabled={disabled}
                    id={id}
                    name={name}
                    onChange={(event) => onCheckedChange(event.target.checked)}
                    ref={(node) => registry?.register(name, node)}
                    type="checkbox"
                />
                {variant === "switch" && (
                    <>
                        <span className={mcClasses("mc-switch__track")} />
                        <span className={mcClasses("mc-switch__thumb")} />
                    </>
                )}
                <span className={mcClasses(variant === "switch" ? "mc-switch__label" : "mc-checkbox__label")}>
                    {label}
                </span>
            </label>
            {help && <div className={mcClasses("mc-field-help")} id={helpId}>{help}</div>}
            {error && (
                <div className={mcClasses("mc-field-error")} id={errorId} role="alert">
                    <i className={mcClasses("bi bi-exclamation-circle mc-field-error__icon")} aria-hidden="true" />
                    <span>{error}</span>
                </div>
            )}
        </div>
    );
}

/* -------------------------------------------------------------------------- */
/* Form shell                                                                 */
/* -------------------------------------------------------------------------- */

/**
 * Standalone inline error message, for hand-rolled fields that cannot yet use
 * the Field primitive. Pair the id with the control's aria-describedby.
 */
export function FieldError({id, message}: {id?: string; message?: string}) {
    if (!message) {
        return null;
    }

    return (
        <div className={mcClasses("mc-field-error")} id={id} role="alert">
            <i className={mcClasses("bi bi-exclamation-circle mc-field-error__icon")} aria-hidden="true" />
            <span>{message}</span>
        </div>
    );
}

/** Accessibility and error-state attributes for a hand-rolled control. */
export const fieldAttrs = (id: string, message?: string, isRequired = false) => ({
    id,
    "aria-describedby": message ? `${id}-error` : undefined,
    "aria-invalid": message ? true : undefined,
    "aria-required": isRequired ? true : undefined,
});

export type McFormSummaryProps = {message?: string};

/** Banner for errors that belong to no single field. */
export function McFormSummary({message}: McFormSummaryProps) {
    if (!message) {
        return null;
    }

    return (
        <div className={mcClasses("mc-alert mc-alert--danger mc-form-summary")} role="alert" tabIndex={-1}>
            <i className={mcClasses("bi bi-exclamation-triangle mc-alert__icon")} aria-hidden="true" />
            <div className={mcClasses("mc-alert__body")}>{message}</div>
        </div>
    );
}

export type McFormProps = {
    id: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    children: ReactNode;
    className?: string;
    /** Registry from useFormValidation, so focus-first-error can find controls. */
    registry?: FieldRegistry;
};

/**
 * Always sets noValidate: the browser's own bubble would otherwise fire first
 * and hide the inline messages. Native `required` is still emitted on controls
 * as a progressive-enhancement fallback.
 */
export function McForm({id, onSubmit, children, className, registry}: McFormProps) {
    const fallback = useFieldRegistry();

    return (
        <FormValidationContext.Provider value={registry ?? fallback}>
            <form className={className} id={id} noValidate onSubmit={onSubmit}>
                {children}
            </form>
        </FormValidationContext.Provider>
    );
}

/**
 * Provides a registry to Fields that are not wrapped in a McForm — drawers whose
 * submit lives on a footer button, and sub-forms that cannot nest a <form>.
 */
export function McFieldScope({registry, children}: {registry: FieldRegistry; children: ReactNode}) {
    return (
        <FormValidationContext.Provider value={registry}>
            {children}
        </FormValidationContext.Provider>
    );
}

/* -------------------------------------------------------------------------- */
/* Validation hook                                                            */
/* -------------------------------------------------------------------------- */

export type UseFormValidationOptions<T extends Record<string, unknown>> = {
    values: T;
    rules: RuleMap<T>;
    /** Reveal the panel owning a field before focusing it, for tabbed forms. */
    onFocusField?: (field: string) => void;
    /** Whole-form checks that cannot be expressed as a single-field rule. */
    validateForm?: (values: T) => FieldErrorMap | undefined;
};

export type UseFormValidationResult<T extends Record<string, unknown>> = {
    errors: FieldErrorMap;
    errorFor: (field: Extract<keyof T, string>) => string | undefined;
    formError: string | undefined;
    submitted: boolean;
    handleSubmit: (handler: (values: T) => void | Promise<void>) =>
        (event?: FormEvent<HTMLFormElement>) => Promise<void>;
    applyServerErrors: (response: SaveResponseLike) => FieldErrorMap;
    setFormError: (message: string) => void;
    clearError: (field: Extract<keyof T, string>) => void;
    reset: () => void;
    /** Pass to <McForm registry={…}> or <McFieldScope registry={…}>. */
    registry: FieldRegistry;
};

export function useFormValidation<T extends Record<string, unknown>>({
    values,
    rules,
    onFocusField,
    validateForm,
}: UseFormValidationOptions<T>): UseFormValidationResult<T> {
    const registry = useFieldRegistry();
    const [submitted, setSubmitted] = useState(false);
    const [serverErrors, setServerErrors] = useState<FieldErrorMap>({});

    // Values as they stood when the server last replied. A server error is
    // dropped as soon as its field changes, so "SKU already in use" clears when
    // the user edits the SKU rather than lingering until the next save.
    const serverSnapshot = useRef<T | null>(null);

    const liveServerErrors = useMemo(() => {
        const snapshot = serverSnapshot.current;
        if (!snapshot) {
            return serverErrors;
        }
        const kept: FieldErrorMap = {};
        Object.keys(serverErrors).forEach((field) => {
            if (field === FORM_ERROR_KEY || Object.is(snapshot[field], values?.[field as keyof T])) {
                kept[field] = serverErrors[field];
            }
        });
        return kept;
    }, [serverErrors, values]);

    // Nothing is red until the first submit; after that every keystroke
    // revalidates, so an error clears as soon as it is fixed.
    const clientErrors = useMemo(() => {
        if (!submitted) {
            return {};
        }
        return {...runRules(values, rules), ...(validateForm?.(values) ?? {})};
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [submitted, values, rules, validateForm]);

    const errors = useMemo(
        () => ({...liveServerErrors, ...clientErrors}),
        [liveServerErrors, clientErrors],
    );

    const focusFirst = useCallback((found: FieldErrorMap) => {
        // Rule declaration order, not Object.keys(errors) — the latter is
        // insertion-ordered and would jump around between submits.
        const order = Object.keys(rules) as string[];
        const first = order.find((field) => found[field]) ?? Object.keys(found).find((f) => f !== FORM_ERROR_KEY);
        if (!first) {
            return;
        }

        onFocusField?.(first);

        // The node may not exist until React commits the revealed panel.
        window.requestAnimationFrame(() => {
            const node = registry.lookup(first) ?? document.getElementsByName(first)[0] ?? null;
            if (!node) {
                return;
            }
            node.scrollIntoView({block: "center", behavior: "smooth"});
            (node as HTMLElement).focus({preventScroll: true});
        });
    }, [registry, rules, onFocusField]);

    const handleSubmit = useCallback((handler: (values: T) => void | Promise<void>) =>
        async(event?: FormEvent<HTMLFormElement>) => {
            event?.preventDefault();
            setSubmitted(true);

            const found = {...runRules(values, rules), ...(validateForm?.(values) ?? {})};
            if (Object.keys(found).length > 0) {
                focusFirst(found);
                return;
            }

            await handler(values);
        }, [values, rules, validateForm, focusFirst]);

    const applyServerErrors = useCallback((response: SaveResponseLike): FieldErrorMap => {
        const map = normaliseServerErrors(response?.errors);
        serverSnapshot.current = values;
        setServerErrors(map);
        if (Object.keys(map).length > 0) {
            focusFirst(map);
        }
        return map;
    }, [values, focusFirst]);

    const setFormError = useCallback((message: string) => {
        serverSnapshot.current = values;
        setServerErrors((current) => ({...current, [FORM_ERROR_KEY]: message}));
    }, [values]);

    const clearError = useCallback((field: Extract<keyof T, string>) => {
        setServerErrors((current) => {
            if (!current[field]) {
                return current;
            }
            const next = {...current};
            delete next[field];
            return next;
        });
    }, []);

    const reset = useCallback(() => {
        setSubmitted(false);
        setServerErrors({});
        serverSnapshot.current = null;
    }, []);

    return {
        errors,
        errorFor: (field) => errors[field],
        formError: errors[FORM_ERROR_KEY],
        submitted,
        handleSubmit,
        applyServerErrors,
        setFormError,
        clearError,
        reset,
        registry,
    };
}

/**
 * Lighter adapter for surfaces that only need to render server-supplied field
 * errors without a client rule set.
 */
export function useFieldErrors() {
    const [errors, setErrors] = useState<FieldErrorMap>({});

    return {
        errors,
        errorFor: (field: string) => errors[field],
        formError: errors[FORM_ERROR_KEY],
        setFromResponse: (response: SaveResponseLike): FieldErrorMap => {
            const map = normaliseServerErrors(response?.errors);
            setErrors(map);
            return map;
        },
        setErrors,
        clear: () => setErrors({}),
    };
}
