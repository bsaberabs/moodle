// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner certificates page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_certificates
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {useEffect, useState} from "react";
import {callMoodleService, Labels} from "./learner_common";
import {mcClasses, useModernCommerceClassSync} from "./design_system";
import ModernLearnerLayout, {type LearnerLayoutContext} from "./learner_layout";
import {LearnerStatStrip, LearnerStatTile} from "./learner_stat_tiles";

type Certificate = {
    id: number;
    courseid: number;
    coursename: string;
    templatename: string;
    code: string;
    issueddate: string;
    expiresdate: string;
    expired: boolean;
    viewurl: string;
};

type CertificateStats = {
    total: number;
    courses: number;
    active: number;
    latestissued: string;
};

type CertificatesResponse = {
    success: boolean;
    available: boolean;
    message: string;
    certificates: Certificate[];
    stats: CertificateStats;
    urls: {
        catalog: string;
        dashboard: string;
        certificates: string;
    };
};

type LearnerCertificatesProps = {
    methodName: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
};

function label(labels: Labels, key: string): string {
    return labels[key] || key;
}

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-patch-check" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{label(labels, "loading")}</p>
        </div>
    );
}

function EmptyState({
    labels,
    catalogUrl,
}: {
    labels: Labels;
    catalogUrl: string;
}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-patch-check" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{label(labels, "nocertificates")}</p>
            <p className={mcClasses("mc-empty__desc")}>
                {label(labels, "certificateemptydesc")}
            </p>
            <a className={mcClasses("mc-button btn-mc-primary")} href={catalogUrl}>
                <i className="bi bi-grid me-1" aria-hidden="true" />
                {label(labels, "browsecatalog")}
            </a>
        </div>
    );
}

function UnavailableState({
    labels,
    message,
}: {
    labels: Labels;
    message: string;
}) {
    return (
        <div className={mcClasses("mc-alert mc-alert--info")} role="status">
            <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
            <div className={mcClasses("mc-alert__body")}>
                <strong>{label(labels, "certificatefeaturesunavailable")}</strong>
                <div>
                    {message || label(labels, "certificatefeaturesunavailabledesc")}
                </div>
            </div>
        </div>
    );
}

function CertificatesTable({
    certificates,
    labels,
}: {
    certificates: Certificate[];
    labels: Labels;
}) {
    return (
        <div className={mcClasses("mc-card")}>
            <div className={mcClasses("mc-card-header")}>
                <div>
                    <h2>{label(labels, "mycertificates")}</h2>
                    <p>{label(labels, "certificatedesc")}</p>
                </div>
            </div>
            <div className={mcClasses("mc-card-body p-0")}>
                <div className="table-responsive">
                    <table className="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{label(labels, "course")}</th>
                                <th scope="col">{label(labels, "certificatetemplate")}</th>
                                <th scope="col">{label(labels, "certificatecode")}</th>
                                <th scope="col">{label(labels, "issueddate")}</th>
                                <th scope="col">{label(labels, "expiresdate")}</th>
                                <th scope="col" className="text-end">{label(labels, "actions")}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {certificates.map((certificate) => (
                                <tr key={certificate.id}>
                                    <td>
                                        <div className="fw-semibold">{certificate.coursename}</div>
                                        <div className={mcClasses("mc-cell-muted small")}>{certificate.templatename}</div>
                                    </td>
                                    <td>{certificate.templatename}</td>
                                    <td>
                                        <code>{certificate.code}</code>
                                    </td>
                                    <td>{certificate.issueddate}</td>
                                    <td>
                                        {certificate.expiresdate ? (
                                            <span className={certificate.expired ? "text-danger" : undefined}>
                                                {certificate.expiresdate}
                                            </span>
                                        ) : (
                                            <span className={mcClasses("mc-cell-muted")}>
                                                {label(labels, "noexpiry")}
                                            </span>
                                        )}
                                    </td>
                                    <td className="text-end">
                                        <a
                                            className={mcClasses("mc-button btn-mc-secondary py-1 px-2")}
                                            href={certificate.viewurl}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <i className="bi bi-box-arrow-up-right me-1" aria-hidden="true" />
                                            {label(labels, "viewcertificate")}
                                        </a>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

export default function LearnerCertificates({
    methodName,
    labels,
    layout,
}: LearnerCertificatesProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<CertificatesResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        setLoading(true);
        setError("");

        callMoodleService<CertificatesResponse>(methodName, {})
            .then((result) => {
                if (!cancelled) {
                    setData(result);
                }
                return result;
            })
            .catch((caught: Error) => {
                if (!cancelled) {
                    setError(caught.message);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [methodName]);

    const stats = data?.stats ?? {
        total: 0,
        courses: 0,
        active: 0,
        latestissued: "",
    };
    const certificates = data?.certificates ?? [];
    const catalogUrl = data?.urls.catalog ?? "#";
    const actions = (
        <a className={mcClasses("mc-button btn-mc-secondary")} href={catalogUrl}>
            <i className="bi bi-grid" aria-hidden="true" />
            {label(labels, "browsecatalog")}
        </a>
    );

    return (
        <ModernLearnerLayout
            activeNav="certificates"
            title={label(labels, "mycertificates")}
            subtitle={label(labels, "certificatedesc")}
            labels={labels}
            layout={layout}
            actions={actions}
        >
            <div className={mcClasses("mc-learner-certificates")}>
                <LearnerStatStrip>
                    <LearnerStatTile
                        label={label(labels, "certificatesearned")}
                        value={stats.total}
                        icon="bi-patch-check-fill"
                        variant="primary"
                    />
                    <LearnerStatTile
                        label={label(labels, "activecertificates")}
                        value={stats.active}
                        icon="bi-shield-check"
                        variant="success"
                    />
                    <LearnerStatTile
                        label={label(labels, "certificatecourses")}
                        value={stats.courses}
                        icon="bi-mortarboard"
                        variant="info"
                    />
                    <LearnerStatTile
                        label={label(labels, "latestcertificate")}
                        value={stats.latestissued || "-"}
                        icon="bi-calendar-check"
                        variant="warning"
                    />
                </LearnerStatStrip>

                {loading && <LoadingState labels={labels} />}

                {!loading && error && (
                    <div className={mcClasses("mc-alert mc-alert--warning")} role="alert">
                        <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                        <div className={mcClasses("mc-alert__body")}>{error}</div>
                    </div>
                )}

                {!loading && !error && data && !data.available && (
                    <UnavailableState labels={labels} message={data.message} />
                )}

                {!loading && !error && data?.available && certificates.length === 0 && (
                    <EmptyState labels={labels} catalogUrl={catalogUrl} />
                )}

                {!loading && !error && data?.available && certificates.length > 0 && (
                    <CertificatesTable certificates={certificates} labels={labels} />
                )}
            </div>
        </ModernLearnerLayout>
    );
}
