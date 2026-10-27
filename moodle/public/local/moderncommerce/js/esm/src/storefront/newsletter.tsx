// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Newsletter / lead-capture widget for the Modern Commerce storefront.
 *
 * @module     local_moderncommerce/storefront/newsletter
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CSSProperties, FormEvent, useState} from "react";
import {McButton} from "../button";
// Shared so every surface agrees on what a valid address looks like.
import {EMAIL_RE} from "../validation";
import RecaptchaField, {
    getRecaptchaResponse,
    recaptchaConfigDefaults,
    RecaptchaConfig,
    resetRecaptcha,
} from "./recaptcha";

declare const M: {
    cfg: {
        sesskey: string;
        wwwroot: string;
    };
};

type NewsletterProps = {
    method: string;
    heading: string;
    description: string;
    placeholder: string;
    buttonlabel: string;
    successmessage: string;
    recaptcha: RecaptchaConfig;
    labels: {emailrequired: string; invalidemail: string; subscribing: string; servicerequestfailed: string};
    style?: CSSProperties;
};

const callService = async <T, >(methodName: string, args: unknown, serviceErrorMessage: string): Promise<T> => {
    const query = new URLSearchParams({sesskey: M.cfg.sesskey, info: methodName});
    const response = await fetch(`${M.cfg.wwwroot}/lib/ajax/service.php?${query.toString()}`, {
        method: "POST",
        credentials: "same-origin",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify([{index: 0, methodname: methodName, args}]),
    });
    if (!response.ok) {
        throw new Error(`${response.status} ${response.statusText}`);
    }
    const payload = await response.json();
    const first = Array.isArray(payload) ? payload[0] : payload;
    if (!first) {
        throw new Error("Empty Moodle service response.");
    }
    if (first.error) {
        throw new Error(first.exception?.message ?? serviceErrorMessage);
    }
    return (first.data ?? first) as T;
};

export default function NewsletterForm({
    method,
    heading,
    description,
    placeholder,
    buttonlabel,
    successmessage,
    recaptcha = recaptchaConfigDefaults(),
    labels,
    style,
}: NewsletterProps) {
    const [email, setEmail] = useState("");
    const [status, setStatus] = useState<"idle" | "busy" | "done" | "error">("idle");
    const [message, setMessage] = useState("");

    const submit = async(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const form = event.currentTarget;
        const value = email.trim();
        if (!value) {
            setStatus("error");
            setMessage(labels.emailrequired);
            return;
        }
        if (!EMAIL_RE.test(value)) {
            setStatus("error");
            setMessage(labels.invalidemail);
            return;
        }
        const recaptcharesponse = recaptcha.enabled ? getRecaptchaResponse(form) : "";
        if (recaptcha.enabled && !recaptcharesponse) {
            setStatus("error");
            setMessage(recaptcha.requiredmessage);
            return;
        }

        setStatus("busy");
        setMessage("");
        try {
            const result = await callService<{success: boolean; message: string}>(method, {
                email: value,
                source: "storefront",
                recaptcharesponse,
            }, labels.servicerequestfailed);
            if (result.success) {
                setStatus("done");
                setMessage(result.message || successmessage);
                setEmail("");
            } else {
                setStatus("error");
                setMessage(result.message);
                resetRecaptcha(form);
            }
        } catch (caught) {
            setStatus("error");
            setMessage((caught as Error).message);
            resetRecaptcha(form);
        }
    };

    return (
        <section className="mw-news" style={style}>
            <div className="mw-news__inner">
                {heading && <h2 className="mw-news__heading">{heading}</h2>}
                {description && <p className="mw-news__desc">{description}</p>}

                {status === "done" ? (
                    <p className="mw-news__success">
                        <i className="bi bi-check-circle-fill" aria-hidden="true" /> {message}
                    </p>
                ) : (
                    <form className="mw-news__form" onSubmit={submit}>
                        <input
                            type="email"
                            className="mw-news__input"
                            placeholder={placeholder}
                            aria-label={placeholder}
                            value={email}
                            onChange={(event) => setEmail(event.currentTarget.value)}
                        />
                        <RecaptchaField config={recaptcha} className="mw-news__captcha" />
                        <McButton type="submit" className="mw-news__btn" variant="soft" loading={status === "busy"} loadingLabel={labels.subscribing}>
                            {buttonlabel}
                        </McButton>
                    </form>
                )}

                {status === "error" && message && <p className="mw-news__error">{message}</p>}
            </div>
        </section>
    );
}
