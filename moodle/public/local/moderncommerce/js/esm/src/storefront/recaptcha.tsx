// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React wrapper for Moodle's configured Google reCAPTCHA v2 widget.
 *
 * @module     local_moderncommerce/storefront/recaptcha
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useRef, useState} from "react";

export type RecaptchaConfig = {
    enabled: boolean;
    sitekey: string;
    apiurl: string;
    lang: string;
    requiredmessage: string;
    errormessage: string;
};

type Grecaptcha = {
    render: (container: HTMLElement, options: {sitekey: string}) => number;
    reset: (widgetId?: number) => void;
};

declare global {
    interface Window {
        grecaptcha?: Grecaptcha;
        localModernCommerceRecaptchaLoaded?: () => void;
        localModernCommerceRecaptchaPromise?: Promise<void>;
    }
}

const SCRIPT_ID = "local-moderncommerce-recaptcha-api";
const CALLBACK_NAME = "localModernCommerceRecaptchaLoaded";

export const recaptchaConfigDefaults = (): RecaptchaConfig => ({
    enabled: false,
    sitekey: "",
    apiurl: "",
    lang: "",
    requiredmessage: "",
    errormessage: "",
});

const loadRecaptcha = (apiUrl: string, lang: string): Promise<void> => {
    if (window.grecaptcha?.render) {
        return Promise.resolve();
    }

    if (window.localModernCommerceRecaptchaPromise) {
        return window.localModernCommerceRecaptchaPromise;
    }

    window.localModernCommerceRecaptchaPromise = new Promise((resolve, reject) => {
        window.localModernCommerceRecaptchaLoaded = () => resolve();

        const existing = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null;
        if (existing) {
            existing.addEventListener("load", () => resolve(), {once: true});
            existing.addEventListener("error", () => reject(new Error("reCAPTCHA failed to load.")), {once: true});
            return;
        }

        const url = new URL(apiUrl, window.location.href);
        url.searchParams.set("onload", CALLBACK_NAME);
        url.searchParams.set("render", "explicit");
        if (lang) {
            url.searchParams.set("hl", lang);
        }

        const script = document.createElement("script");
        script.id = SCRIPT_ID;
        script.src = url.toString();
        script.async = true;
        script.defer = true;
        script.onerror = () => reject(new Error("reCAPTCHA failed to load."));
        document.head.appendChild(script);
    });

    return window.localModernCommerceRecaptchaPromise;
};

export const getRecaptchaResponse = (form: HTMLFormElement): string => {
    const value = new FormData(form).get("g-recaptcha-response");
    return typeof value === "string" ? value.trim() : "";
};

export const resetRecaptcha = (form: HTMLFormElement): void => {
    form.querySelectorAll<HTMLElement>("[data-mc-recaptcha-widget-id]").forEach((element) => {
        const widgetId = Number(element.dataset.mcRecaptchaWidgetId);
        if (Number.isFinite(widgetId)) {
            window.grecaptcha?.reset(widgetId);
        }
    });
};

export default function RecaptchaField({
    config,
    className = "",
}: {
    config: RecaptchaConfig;
    className?: string;
}) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const widgetIdRef = useRef<number | null>(null);
    const [error, setError] = useState("");

    useEffect(() => {
        if (!config.enabled || !config.sitekey || !config.apiurl || !containerRef.current) {
            return undefined;
        }

        let cancelled = false;
        setError("");

        loadRecaptcha(config.apiurl, config.lang)
            .then(() => {
                if (cancelled || !containerRef.current || widgetIdRef.current !== null || !window.grecaptcha?.render) {
                    return;
                }
                widgetIdRef.current = window.grecaptcha.render(containerRef.current, {
                    sitekey: config.sitekey,
                });
                containerRef.current.dataset.mcRecaptchaWidgetId = String(widgetIdRef.current);
            })
            .catch(() => setError(config.errormessage));

        return () => {
            cancelled = true;
        };
    }, [config.enabled, config.sitekey, config.apiurl, config.lang, config.errormessage]);

    if (!config.enabled) {
        return null;
    }

    return (
        <div className={className || undefined}>
            <div ref={containerRef} />
            {error && <p className="mc-recaptcha-error">{error}</p>}
        </div>
    );
}
