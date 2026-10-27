// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Admin documentation center for Modern Commerce.
 *
 * @module     local_moderncommerce/admin_help
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {type MouseEvent, useCallback, useEffect, useMemo, useRef, useState} from "react";
import {useModernCommerceClassSync} from "./design_system";

declare const M: {
    cfg: {
        wwwroot: string;
    };
};

type HelpGroup = {
    id: string;
    title: string;
    icon: string;
};

type HelpDocument = {
    id: string;
    group: string;
    title: string;
    summary: string;
    icon: string;
    file: string;
    html: string;
    sourceurl: string;
    searchtext: string;
};

type Labels = Record<string, string>;

type Props = {
    groups: HelpGroup[];
    documents: HelpDocument[];
    exitUrl: string;
    labels: Labels;
};

const label = (labels: Labels, key: string, fallback: string): string => labels[key] || fallback;

const normalizedHash = (): string => {
    try {
        return decodeURIComponent(window.location.hash.replace(/^#/, "")).trim();
    } catch {
        return window.location.hash.replace(/^#/, "").trim();
    }
};

const estimateReadMinutes = (document: HelpDocument): number => {
    const words = document.searchtext.split(/\s+/).filter(Boolean).length;
    return Math.max(1, Math.round(words / 220));
};

export default function AdminHelp({groups, documents, exitUrl, labels}: Props) {
    useModernCommerceClassSync();

    const [query, setQuery] = useState("");
    const [copied, setCopied] = useState(false);
    const readerRef = useRef<HTMLElement | null>(null);
    const [activeId, setActiveId] = useState(() => {
        const hash = normalizedHash();
        return documents.some((document) => document.id === hash) ? hash : documents[0]?.id ?? "";
    });

    const documentsById = useMemo(() => {
        return new Map(documents.map((document) => [document.id, document]));
    }, [documents]);

    const groupById = useMemo(() => {
        return new Map(groups.map((group) => [group.id, group]));
    }, [groups]);

    const scrollReaderToTop = useCallback(() => {
        window.requestAnimationFrame(() => {
            readerRef.current?.scrollIntoView({
                block: "start",
                inline: "nearest",
                behavior: "auto",
            });
        });
    }, []);

    useEffect(() => {
        const onHashChange = () => {
            const hash = normalizedHash();
            if (documentsById.has(hash)) {
                setActiveId(hash);
                setCopied(false);
                scrollReaderToTop();
            }
        };

        window.addEventListener("hashchange", onHashChange);
        return () => window.removeEventListener("hashchange", onHashChange);
    }, [documentsById, scrollReaderToTop]);

    useEffect(() => {
        if (activeId && documentsById.has(activeId)) {
            return;
        }
        setActiveId(documents[0]?.id ?? "");
    }, [activeId, documents, documentsById]);

    const activateDocument = (id: string) => {
        if (!documentsById.has(id)) {
            return;
        }
        setActiveId(id);
        setCopied(false);
        if (window.location.hash !== `#${id}`) {
            window.history.pushState(null, "", `#${id}`);
        }
        scrollReaderToTop();
    };

    const searchTerm = query.trim().toLowerCase();
    const filteredDocuments = useMemo(() => {
        if (searchTerm === "") {
            return documents;
        }
        return documents.filter((document) => {
            return [
                document.title,
                document.summary,
                groupById.get(document.group)?.title ?? "",
                document.file,
                document.searchtext,
            ].join(" ").toLowerCase().includes(searchTerm);
        });
    }, [documents, groupById, searchTerm]);

    const activeDocument = documentsById.get(activeId) ?? documents[0] ?? null;

    useEffect(() => {
        if (searchTerm === "" || filteredDocuments.length === 0) {
            return;
        }
        if (filteredDocuments.some((document) => document.id === activeId)) {
            return;
        }

        const nextId = filteredDocuments[0].id;
        setActiveId(nextId);
        if (window.location.hash !== `#${nextId}`) {
            window.history.replaceState(null, "", `#${nextId}`);
        }
    }, [activeId, filteredDocuments, searchTerm]);

    const visibleByGroup = (groupId: string): HelpDocument[] => {
        const source = searchTerm === "" ? documents : filteredDocuments;
        return source.filter((document) => document.group === groupId);
    };

    const activeUrl = activeDocument
        ? `${M.cfg.wwwroot}/local/moderncommerce/admin/help/#${activeDocument.id}`
        : `${M.cfg.wwwroot}/local/moderncommerce/admin/help/`;

    const copyActiveLink = async () => {
        if (!activeDocument || !navigator.clipboard) {
            return;
        }

        await navigator.clipboard.writeText(activeUrl);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1800);
    };

    const handleContentClick = (event: MouseEvent<HTMLElement>) => {
        const target = (event.target as HTMLElement).closest("a");
        if (!target) {
            return;
        }
        const href = target.getAttribute("href") ?? "";
        if (!href.startsWith("#")) {
            return;
        }
        const targetId = href.slice(1);
        if (!documentsById.has(targetId)) {
            return;
        }
        event.preventDefault();
        activateDocument(targetId);
    };

    return (
        <div className="mch-app">
            <aside className="mch-sidebar" aria-label={label(labels, "sidebarnav", "Documentation")}>
                <div className="mch-brand">
                    <span className="mch-brand__mark local-moderncommerce-admin-sidebar__logo" aria-hidden="true">
                        <i className="bi bi-journal-text" />
                    </span>
                    <div>
                        <strong>Modern Commerce</strong>
                        <small>{label(labels, "sidebarnav", "Documentation")}</small>
                    </div>
                </div>

                <section className="mch-nav-search" aria-label={label(labels, "searchlabel", "Search documentation")}>
                    <label className="visually-hidden" htmlFor="mch-doc-search">
                        {label(labels, "searchlabel", "Search documentation")}
                    </label>
                    <div className="mch-nav-search__control">
                        <i className="bi bi-search" aria-hidden="true" />
                        <input
                            id="mch-doc-search"
                            type="search"
                            value={query}
                            placeholder={label(labels, "searchplaceholder", "Search setup, payments, widgets, CLI...")}
                            onChange={(event) => setQuery(event.currentTarget.value)}
                        />
                        {query !== "" && (
                            <button type="button" className="mch-nav-search__clear" onClick={() => setQuery("")}>
                                {label(labels, "clearsearch", "Clear")}
                            </button>
                        )}
                    </div>
                </section>

                <div className="mch-nav__summary">
                    <span>{searchTerm === "" ? label(labels, "alltopics", "All topics") : label(labels, "results", "Results")}</span>
                    <strong>{filteredDocuments.length}</strong>
                </div>

                <nav className="mch-nav">
                    {filteredDocuments.length === 0 ? (
                        <div className="mch-nav-empty">
                            <i className="bi bi-search" aria-hidden="true" />
                            <p>{label(labels, "noresults", "No documentation matched your search.")}</p>
                        </div>
                    ) : (
                        groups.map((group) => {
                            const groupDocuments = visibleByGroup(group.id);
                            if (groupDocuments.length === 0) {
                                return null;
                            }
                            return (
                                <section className="mch-nav__group" key={group.id}>
                                    <h2>
                                        <i className={`bi ${group.icon}`} aria-hidden="true" />
                                        <span>{group.title}</span>
                                    </h2>
                                    {groupDocuments.map((document) => (
                                        <button
                                            type="button"
                                            key={document.id}
                                            className={`mch-nav__item${document.id === activeDocument?.id ? " is-active" : ""}`}
                                            onClick={() => activateDocument(document.id)}
                                        >
                                            <i className={`bi ${document.icon}`} aria-hidden="true" />
                                            <span>{document.title}</span>
                                        </button>
                                    ))}
                                </section>
                            );
                        })
                    )}
                </nav>
            </aside>

            <main className="mch-main">
                <header className="mch-hero">
                    <div className="mch-hero__copy">
                        <span className="mch-hero__eyebrow">{label(labels, "eyebrow", "Admin guide")}</span>
                        <h1>{label(labels, "title", "Documentation")}</h1>
                        <p>{label(labels, "intro", "Find setup, workflow, operations, and release documentation.")}</p>
                    </div>
                    <div className="mch-hero__actions">
                        <a className="mch-btn mch-btn--soft" href={exitUrl}>
                            <i className="bi bi-arrow-left" aria-hidden="true" />
                            <span>{label(labels, "exit", "Exit")}</span>
                        </a>
                    </div>
                </header>

                <article className="mch-reader" ref={readerRef} aria-live="polite">
                    {activeDocument && (
                        <>
                            <div className="mch-reader__bar">
                                <div>
                                    <span className="mch-reader__meta">
                                        {groupById.get(activeDocument.group)?.title ?? activeDocument.group}
                                        {" · "}
                                        {estimateReadMinutes(activeDocument)} {label(labels, "readtime", "min read")}
                                    </span>
                                    <h2>{activeDocument.title}</h2>
                                    <p>{activeDocument.summary}</p>
                                </div>
                                <div className="mch-reader__actions">
                                    <button type="button" className="mch-btn mch-btn--soft" onClick={copyActiveLink}>
                                        <i className="bi bi-link-45deg" aria-hidden="true" />
                                        <span>{copied ? label(labels, "copied", "Copied") : label(labels, "copylink", "Copy link")}</span>
                                    </button>
                                    <a
                                        className="mch-btn mch-btn--soft"
                                        href={activeDocument.sourceurl}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <i className="bi bi-box-arrow-up-right" aria-hidden="true" />
                                        <span>{label(labels, "opensource", "Open source")}</span>
                                    </a>
                                </div>
                            </div>
                            <div
                                className="mch-prose"
                                onClick={handleContentClick}
                                dangerouslySetInnerHTML={{__html: activeDocument.html}}
                            />
                        </>
                    )}
                </article>
            </main>
        </div>
    );
}
