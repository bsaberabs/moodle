// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * Site-wide multi-column storefront footer for Modern Commerce.
 *
 * Five-column marketing footer: brand + contact, three link columns, and an
 * app-download column, over a bottom bar with the copyright and social icons.
 * Light/dark palettes are driven by the `mode` setting (mc-footer--dark class).
 *
 * @module     local_moderncommerce/storefront/footer
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

type FooterLink = {label: string; url: string};
type FooterColumn = {title: string; links: FooterLink[]};
type FooterSocial = {icon: string; url: string; label: string};
type FooterLabels = Record<string, string>;
type CSSProperties = import("react").CSSProperties;

declare const M: { cfg?: { wwwroot?: string } } | undefined;

export type FooterData = {
    style: "default" | "modern-classical" | "enterprise-navy";
    mode: "light" | "dark";
    logo: string;
    logoheight: number;
    bgcolor: string;
    panelbgcolor: string;
    titlecolor: string;
    titlefontsize: number;
    textcolor: string;
    textfontsize: number;
    linkcolor: string;
    iconbgcolor: string;
    iconcolor: string;
    inputbgcolor: string;
    inputbordercolor: string;
    inputtextcolor: string;
    buttoncolor: string;
    buttontextcolor: string;
    paddingtop: number;
    paddingbottom: number;
    brandname: string;
    description: string;
    address: string[];
    phone: string;
    email: string;
    languagelabel: string;
    subscribeplaceholder: string;
    compliancelabel: string;
    columns: FooterColumn[];
    appstitle: string;
    googleplayurl: string;
    appstoreurl: string;
    social: FooterSocial[];
    copyright: string;
    labels: FooterLabels;
};

export const footerDataDefaults = (): FooterData => ({
    style: "default", mode: "light", logo: "", logoheight: 42, brandname: "", description: "",
    bgcolor: "", panelbgcolor: "", titlecolor: "", titlefontsize: 0, textcolor: "", textfontsize: 0,
    linkcolor: "", iconbgcolor: "", iconcolor: "", inputbgcolor: "", inputbordercolor: "", inputtextcolor: "",
    buttoncolor: "", buttontextcolor: "", paddingtop: 0, paddingbottom: 0,
    address: [], phone: "", email: "", languagelabel: "",
    subscribeplaceholder: "", compliancelabel: "", columns: [],
    appstitle: "", googleplayurl: "", appstoreurl: "", social: [], copyright: "", labels: {},
});

// Bootstrap-icon value may arrive bare or prefixed; strip any prefix.
const normIcon = (v: string): string => (v || "").trim().replace(/^bi\s+/, "").replace(/^bi-/, "");
const label = (labels: FooterLabels, key: string): string => labels[key] || "";

const withDefaultLegalLinks = (links: FooterLink[], labels: FooterLabels, limit = 3): FooterLink[] => {
    const root = typeof M !== "undefined" && M?.cfg?.wwwroot ? M.cfg.wwwroot : "";
    const hasPrivacy = links.some((link) => /privacy/i.test(link.label));
    const hasTerms = links.some((link) => /terms|use/i.test(link.label));
    const next = [...links];
    if (!hasPrivacy) {
        next.push({label: label(labels, "privacypolicy"), url: `${root}/local/moderncommerce/privacy.php`});
    }
    if (!hasTerms) {
        next.push({label: label(labels, "termsofuse"), url: `${root}/local/moderncommerce/terms.php`});
    }
    return next.slice(0, limit);
};

const withEnterpriseUtilityLinks = (links: FooterLink[], labels: FooterLabels): FooterLink[] => {
    const root = typeof M !== "undefined" && M?.cfg?.wwwroot ? M.cfg.wwwroot : "";
    const next = withDefaultLegalLinks(links, labels, 5);
    const has = (pattern: RegExp) => next.some((link) => pattern.test(link.label));
    if (!has(/sitemap/i)) {
        next.unshift({label: label(labels, "sitemap"), url: `${root}/local/moderncommerce/index.php`});
    }
    if (!has(/security/i)) {
        next.push({label: label(labels, "security"), url: "#"});
    }
    if (!has(/cookie/i)) {
        next.push({label: label(labels, "cookiepreferences"), url: "#"});
    }
    return next.slice(0, 5);
};

const enterpriseFallbackColumns = (labels: FooterLabels): FooterColumn[] => [
    {
        title: label(labels, "platform"),
        links: [
            {label: label(labels, "coursemarketplace"), url: "#"},
            {label: label(labels, "programmecatalog"), url: "#"},
            {label: label(labels, "teamlearning"), url: "#"},
            {label: label(labels, "bundlesandoffers"), url: "#"},
            {label: label(labels, "certificates"), url: "#"},
        ],
    },
    {
        title: label(labels, "company"),
        links: [
            {label: label(labels, "aboutstore"), url: "#"},
            {label: label(labels, "successstories"), url: "#"},
            {label: label(labels, "partners"), url: "#"},
            {label: label(labels, "careers"), url: "#"},
            {label: label(labels, "contact"), url: "#"},
        ],
    },
    {
        title: label(labels, "popularlinks"),
        links: [
            {label: label(labels, "bestsellingcourses"), url: "#"},
            {label: label(labels, "newprogrammes"), url: "#"},
            {label: label(labels, "corporatetraining"), url: "#"},
            {label: label(labels, "scholarships"), url: "#"},
            {label: label(labels, "events"), url: "#"},
        ],
    },
    {
        title: label(labels, "resources"),
        links: [
            {label: label(labels, "learningguides"), url: "#"},
            {label: label(labels, "resourcelibrary"), url: "#"},
            {label: label(labels, "blog"), url: "#"},
            {label: label(labels, "webinars"), url: "#"},
            {label: label(labels, "helparticles"), url: "#"},
        ],
    },
    {
        title: label(labels, "support"),
        links: [
            {label: label(labels, "contactsupport"), url: "#"},
            {label: label(labels, "helpportal"), url: "#"},
            {label: label(labels, "refundpolicy"), url: "#"},
            {label: label(labels, "privacypolicy"), url: "#"},
            {label: label(labels, "termsofuse"), url: "#"},
        ],
    },
];

const renderSocial = (items: FooterSocial[], className: string, labels: FooterLabels) => {
    if (items.length === 0) {
        return null;
    }
    return (
        <ul className={className} aria-label={label(labels, "socialmedia")}>
            {items.map((s, i) => (
                <li key={i}>
                    <a href={s.url} target="_blank" rel="noopener noreferrer"
                        aria-label={s.label || normIcon(s.icon)}>
                        <i className={`bi bi-${normIcon(s.icon) || "link-45deg"}`} aria-hidden="true" />
                    </a>
                </li>
            ))}
        </ul>
    );
};

function AppBadge({href, icon, eyebrow, name}: {href: string; icon: string; eyebrow: string; name: string}) {
    return (
        <a className="mc-footer__app" href={href} target="_blank" rel="noopener noreferrer">
            <i className={`bi bi-${icon}`} aria-hidden="true" />
            <span className="mc-footer__app-text">
                <span className="mc-footer__app-eyebrow">{eyebrow}</span>
                <span className="mc-footer__app-name">{name}</span>
            </span>
        </a>
    );
}

export default function Footer({data, style: customStyle}: {data: FooterData; style?: CSSProperties}) {
    const hasApps = Boolean(data.googleplayurl || data.appstoreurl);
    const style = data.style === "modern-classical" || data.style === "enterprise-navy" ? data.style : "default";
    const labels = data.labels || {};
    const legalLinks = withDefaultLegalLinks(data.columns
        .flatMap((column) => column.links)
        .filter((link) => /(privacy|terms|policy|use)/i.test(link.label))
        .slice(0, 3), labels);

    if (style === "enterprise-navy") {
        const existingTitles = data.columns.map((column) => column.title.toLowerCase());
        const enterpriseColumns = data.columns.length >= 5
            ? data.columns
            : [
                ...data.columns,
                ...enterpriseFallbackColumns(labels).filter((column) => !existingTitles.includes(column.title.toLowerCase())),
            ].slice(0, 5);
        const utilityLinks = withEnterpriseUtilityLinks(data.columns
            .flatMap((column) => column.links)
            .filter((link) => /(sitemap|privacy|terms|policy|use|legal|security|cookie)/i.test(link.label)), labels);

        return (
            <footer className="mc-footer mc-footer--enterprise-navy" style={customStyle}>
                <div className="mc-footer-en__top">
                    <div className="mc-footer__inner mc-footer-en__inner">
                        <div className="mc-footer-en__columns">
                            {enterpriseColumns.map((col, i) => (
                                <div className="mc-footer-en__col" key={i}>
                                    {col.title && <h2 className="mc-footer-en__title">{col.title}</h2>}
                                    {col.links.length > 0 && (
                                        <ul className="mc-footer-en__links">
                                            {col.links.map((link, j) => (
                                                <li key={j}><a href={link.url}>{link.label}</a></li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            ))}
                        </div>
                        <div className="mc-footer-en__decor" aria-hidden="true">
                            {Array.from({length: 9}).map((_, i) => <span key={i} />)}
                        </div>
                    </div>
                </div>

                <div className="mc-footer-en__bottom">
                    <div className="mc-footer__inner mc-footer-en__bottom-inner">
                        <div className="mc-footer-en__brand">
                            {data.logo
                                ? <img className="mc-footer-en__logo" src={data.logo} alt={data.brandname || label(labels, "logo")}
                                    style={data.logoheight > 0 ? {height: `${data.logoheight}px`} : undefined} />
                                : (data.brandname && <div className="mc-footer-en__brandname">{data.brandname}</div>)}
                            {data.description && <p className="mc-footer-en__description">{data.description}</p>}
                        </div>

                        <div className="mc-footer-en__engage">
                            {data.languagelabel && (
                                <button className="mc-button mc-footer-en__language" data-mc-button="ghost" type="button">
                                    <span>{data.languagelabel}</span>
                                    <i className="bi bi-chevron-down" aria-hidden="true" />
                                </button>
                            )}
                            <div className="mc-footer-en__subscribe" role="group" aria-label={label(labels, "emailsubscription")}>
                                <input type="email" placeholder={data.subscribeplaceholder || label(labels, "subscribeplaceholder")}
                                    aria-label={label(labels, "emailaddress")} />
                                <button className="mc-button mc-footer-en__subscribe-button" data-mc-button="ghost"
                                    data-mc-button-size="icon" type="button" aria-label={label(labels, "subscribe")}>
                                    <i className="bi bi-arrow-right" aria-hidden="true" />
                                </button>
                            </div>
                            <div className="mc-footer-en__social-row">
                                {renderSocial(data.social, "mc-footer-en__social", labels)}
                                {data.compliancelabel && (
                                    <span className="mc-footer-en__compliance">
                                        <i className="bi bi-check-circle" aria-hidden="true" />
                                        {data.compliancelabel}
                                    </span>
                                )}
                            </div>
                        </div>

                        <div className="mc-footer-en__legalblock">
                            {utilityLinks.length > 0 && (
                                <ul className="mc-footer-en__legal">
                                    {utilityLinks.map((link, i) => (
                                        <li key={i}><a href={link.url}>{link.label}</a></li>
                                    ))}
                                </ul>
                            )}
                            {data.copyright && <p className="mc-footer-en__copyright">{data.copyright}</p>}
                        </div>
                    </div>
                </div>
            </footer>
        );
    }

    if (style === "modern-classical") {
        return (
            <footer className="mc-footer mc-footer--modern-classical" style={customStyle}>
                <div className="mc-footer-mc__topline" aria-hidden="true" />
                <div className="mc-footer__inner mc-footer-mc__inner">
                    <div className="mc-footer-mc__grid">
                        <div className="mc-footer-mc__brand">
                            {data.logo
                                ? <img className="mc-footer-mc__logo" src={data.logo} alt={data.brandname || label(labels, "logo")}
                                    style={data.logoheight > 0 ? {height: `${data.logoheight}px`} : undefined} />
                                : (data.brandname && <div className="mc-footer-mc__brandname">{data.brandname}</div>)}
                            {data.description && <p className="mc-footer-mc__description">{data.description}</p>}
                            {renderSocial(data.social, "mc-footer-mc__social", labels)}
                        </div>

                        {data.columns.map((col, i) => (
                            <div className="mc-footer-mc__col" key={i}>
                                {col.title && <h2 className="mc-footer-mc__title">{col.title}</h2>}
                                {col.links.length > 0 && (
                                    <ul className="mc-footer-mc__links">
                                        {col.links.map((link, j) => (
                                            <li key={j}><a href={link.url}>{link.label}</a></li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))}

                        <div className="mc-footer-mc__col mc-footer-mc__contact">
                            <h2 className="mc-footer-mc__title">{label(labels, "contact")}</h2>
                            {data.address.length > 0 && (
                                <address>
                                    {data.address.map((line, i) => <span key={i}>{line}</span>)}
                                </address>
                            )}
                            {data.phone && <a href={`tel:${data.phone.replace(/\s+/g, "")}`}>{data.phone}</a>}
                            {data.email && <a href={`mailto:${data.email}`}>{data.email}</a>}
                        </div>
                    </div>
                </div>

                <div className="mc-footer-mc__bottom">
                    <div className="mc-footer__inner mc-footer-mc__bottom-inner">
                        {data.copyright && <span className="mc-footer-mc__copyright">{data.copyright}</span>}
                        {legalLinks.length > 0 && (
                            <ul className="mc-footer-mc__legal">
                                {legalLinks.map((link, i) => (
                                    <li key={i}><a href={link.url}>{link.label}</a></li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </footer>
        );
    }

    return (
        <footer className={`mc-footer mc-footer--${data.mode === "dark" ? "dark" : "light"}`} style={customStyle}>
            <div className="mc-footer__inner">
                <div className="mc-footer__grid">
                    <div className="mc-footer__col mc-footer__col--brand">
                        {data.logo
                            ? <img className="mc-footer__logo" src={data.logo} alt={data.brandname || label(labels, "logo")}
                                style={data.logoheight > 0 ? {height: `${data.logoheight}px`} : undefined} />
                            : (data.brandname && <div className="mc-footer__brandname">{data.brandname}</div>)}
                        {data.address.length > 0 && (
                            <address className="mc-footer__address">
                                {data.address.map((line, i) => <span key={i}>{line}</span>)}
                            </address>
                        )}
                        <ul className="mc-footer__contact">
                            {data.phone && (
                                <li>
                                    <span className="mc-footer__contact-icon" aria-hidden="true">
                                        <i className="bi bi-telephone" />
                                    </span>
                                    <a href={`tel:${data.phone.replace(/\s+/g, "")}`}>{data.phone}</a>
                                </li>
                            )}
                            {data.email && (
                                <li>
                                    <span className="mc-footer__contact-icon" aria-hidden="true">
                                        <i className="bi bi-envelope" />
                                    </span>
                                    <a href={`mailto:${data.email}`}>{data.email}</a>
                                </li>
                            )}
                        </ul>
                    </div>

                    {data.columns.map((col, i) => (
                        <div className="mc-footer__col mc-footer__col--links" key={i}>
                            {col.title && <h2 className="mc-footer__title">{col.title}</h2>}
                            {col.links.length > 0 && (
                                <ul className="mc-footer__links">
                                    {col.links.map((link, j) => (
                                        <li key={j}><a href={link.url}>{link.label}</a></li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    ))}

                    {hasApps && (
                        <div className="mc-footer__col mc-footer__col--apps">
                            {data.appstitle && <h2 className="mc-footer__title">{data.appstitle}</h2>}
                            <div className="mc-footer__apps">
                                {data.googleplayurl && (
                                    <AppBadge href={data.googleplayurl} icon="google-play"
                                        eyebrow={label(labels, "getitnow")} name="Google Play" />
                                )}
                                {data.appstoreurl && (
                                    <AppBadge href={data.appstoreurl} icon="apple"
                                        eyebrow={label(labels, "nowavailable")} name="App Store" />
                                )}
                            </div>
                        </div>
                    )}
                </div>

                <div className="mc-footer__bottom">
                    {data.copyright && <span className="mc-footer__copyright">{data.copyright}</span>}
                    {renderSocial(data.social, "mc-footer__social", labels)}
                </div>
            </div>
        </footer>
    );
}
