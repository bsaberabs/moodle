// This file is part of Moodle and is licensed under the
// GNU General Public License, version 3 or later.
//
// You may redistribute and modify it under the terms of the GPL.
// See the plugin root LICENSE file for complete terms.

/**
 * React learner profile page for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_profile
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {type Dispatch, type FormEvent, useEffect, useMemo, useState} from "react";
import {callMoodleService, Labels} from "./learner_common";
import {mcClasses, toast, useModernCommerceClassSync} from "./design_system";
import {McButton} from "./button";
import ModernLearnerLayout, {type LearnerLayoutContext, type LearnerLayoutUser} from "./learner_layout";
import {email, fmt, maxLength, normaliseServerErrors, pattern, required, runRules} from "./validation";
import type {RuleMap} from "./validation";

type ProfileDisplayField = {
    inputname: string;
    label: string;
    displayvalue: string;
    hasdisplayvalue: boolean;
};

type Profile = {
    fullname: string;
    firstname: string;
    lastname: string;
    email: string;
    city: string;
    country: string;
    department: string;
    institution: string;
    phone1: string;
    phone2: string;
    address: string;
    idnumber: string;
    timezone: string;
    maildisplay: string;
    language: string;
    description: string;
    interests: string[];
    customfields: ProfileDisplayField[];
};

type Option = {
    value: string;
    label: string;
    selected: boolean;
};

type EditableCustomField = {
    inputname: string;
    label: string;
    datatype: string;
    value: string;
    required: boolean;
    locked: boolean;
    disabled: boolean;
    istext: boolean;
    istextarea: boolean;
    ismenu: boolean;
    ischeckbox: boolean;
    isdatetime: boolean;
    isdateonly: boolean;
    inputtype: string;
    checked?: boolean;
    hasdatetime?: boolean;
    options: Option[];
    minyear: string;
    maxyear: string;
};

type ProfileResponse = {
    success: boolean;
    message: string;
    userid: number;
    profileimage: string;
    profile: Profile;
    canedit: boolean;
    editmessage: string;
    userediturl: string;
    countryoptions: Option[];
    editablecustomfields: EditableCustomField[];
    editable: {
        phone1: string;
        phone2: string;
        address: string;
        idnumber: string;
        timezone: string;
        maildisplay: string;
        lang: string;
        description: string;
    };
    interests: string[];
    timezoneoptions: Option[];
    languageoptions: Option[];
    maildisplayoptions: Option[];
    fieldlocks: Record<string, boolean>;
    urls: {
        profile: string;
        coreprofile: string;
        editprofile: string;
    };
};

type SaveProfileResponse = {
    success: boolean;
    message: string;
    errors: FieldError[];
    profile: Profile;
};

type SavePictureResponse = {
    success: boolean;
    message: string;
    errors: FieldError[];
    profileimage: string;
};

// The server has historically keyed these as `name`; the plugin-wide contract
// is `field`. normaliseServerErrors accepts both, so the two sides can migrate
// independently.
type FieldError = {
    name?: string;
    field?: string;
    message: string;
};

type LearnerProfileProps = {
    getMethodName: string;
    saveMethodName: string;
    savePictureMethodName: string;
    labels: Labels;
    layout?: LearnerLayoutContext;
    /** Site upload limit in bytes; 0 disables the client-side size check. */
    maxImageBytes?: number;
};

type BasicFormState = {
    firstname: string;
    lastname: string;
    email: string;
    city: string;
    country: string;
    department: string;
    institution: string;
    phone1: string;
    phone2: string;
    address: string;
    idnumber: string;
    timezone: string;
    maildisplay: string;
    lang: string;
    description: string;
};

const label = (labels: Labels, key: string, fallback = ""): string => labels[key] || fallback || key;

const initials = (fullname: string): string => fullname
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("") || "LC";

const errorMap = (errors: FieldError[]): Record<string, string> => normaliseServerErrors(errors);

/** Human-readable byte size for the upload-limit message. */
const formatBytes = (bytes: number): string => {
    if (bytes >= 1024 * 1024) {
        return `${Math.round(bytes / (1024 * 1024))} MB`;
    }
    return `${Math.round(bytes / 1024)} KB`;
};

/**
 * Move focus to the first field carrying an error, walking `order` rather than
 * the error map so the landing spot follows the visual layout instead of
 * whichever key happened to be inserted first.
 */
const focusFirstError = (errors: Record<string, string>, order: string[]): void => {
    const first = order.find((field) => errors[field]);
    if (!first) {
        return;
    }

    window.requestAnimationFrame(() => {
        const node = document.getElementById(`mc-profile-${first}`)
            ?? document.getElementById(`mc-profile-custom-${first}`);
        if (!node) {
            return;
        }
        node.scrollIntoView({block: "center", behavior: "smooth"});
        node.focus({preventScroll: true});
    });
};

const fileToBase64 = async(file: File): Promise<string> => {
    const dataUrl = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ""));
        reader.onerror = () => reject(reader.error || new Error("File could not be read."));
        reader.readAsDataURL(file);
    });
    const content = dataUrl.includes(",") ? dataUrl.substring(dataUrl.indexOf(",") + 1) : dataUrl;
    const clean = content.replace(/\s+/g, "");
    return clean.match(/.{1,64}/g)?.join("\n") || clean;
};

const customFieldInitialValue = (field: EditableCustomField): string => {
    if (!field.ischeckbox) {
        return field.value;
    }

    return field.checked ? "1" : "0";
};

const customFieldInputType = (field: EditableCustomField): string => {
    if (!field.isdatetime) {
        return field.inputtype;
    }

    return field.hasdatetime ? "datetime-local" : "date";
};

const customValuesFromFields = (fields: EditableCustomField[]): Record<string, string> => Object.fromEntries(
    fields.map((field) => [field.inputname, customFieldInitialValue(field)])
);

const basicFormStateFromResponse = (result: ProfileResponse, labels: Labels): BasicFormState => {
    const notProvided = label(labels, "notprovided");

    return {
        firstname: result.profile.firstname,
        lastname: result.profile.lastname,
        email: result.profile.email,
        city: result.profile.city === notProvided ? "" : result.profile.city,
        country: result.countryoptions.find((option) => option.selected)?.value ?? "",
        department: result.profile.department === notProvided ? "" : result.profile.department,
        institution: result.profile.institution === notProvided ? "" : result.profile.institution,
        phone1: result.editable.phone1,
        phone2: result.editable.phone2,
        address: result.editable.address,
        idnumber: result.editable.idnumber,
        timezone: result.editable.timezone,
        maildisplay: result.editable.maildisplay,
        lang: result.editable.lang,
        description: result.editable.description,
    };
};

const syncEditableCustomFields = (
    fields: EditableCustomField[],
    values: Record<string, string>,
): EditableCustomField[] => fields.map((field) => {
    const value = values[field.inputname];

    if (typeof value === "undefined") {
        return field;
    }

    return {
        ...field,
        value,
        checked: field.ischeckbox ? value === "1" : field.checked,
        options: field.options.map((option) => ({
            ...option,
            selected: option.value === value,
        })),
    };
});

function LoadingState({labels}: {labels: Labels}) {
    return (
        <div className={mcClasses("mc-empty mc-empty--centered")}>
            <span className={mcClasses("mc-empty__icon")}><i className="bi bi-person-fill" aria-hidden="true" /></span>
            <p className={mcClasses("mc-empty__title")}>{label(labels, "loading")}</p>
        </div>
    );
}

function FieldErrorText({id, message}: {id?: string; message?: string}) {
    if (!message) {
        return null;
    }

    // Was Bootstrap's invalid-feedback, which the mc-* system does not style —
    // it only rendered because the Moodle theme happens to ship Bootstrap.
    return (
        <div className={mcClasses("mc-field-error")} id={id} role="alert">
            <i className={mcClasses("bi bi-exclamation-circle mc-field-error__icon")} aria-hidden="true" />
            <span>{message}</span>
        </div>
    );
}

/** Accessibility and error-state attributes for a hand-rolled profile input. */
const fieldState = (id: string, message?: string, isRequired = false) => ({
    "aria-describedby": message ? `${id}-error` : undefined,
    "aria-invalid": message ? true : undefined,
    "aria-required": isRequired ? true : undefined,
    className: mcClasses("form-control", message ? "mc-field-invalid" : ""),
});

/** Label text with the required marker, matching the shared Field primitive. */
function RequiredMark() {
    return <span className={mcClasses("mc-required")} aria-hidden="true">*</span>;
}

function ProfileDetails({
    profile,
    labels,
    canEdit,
    editMessage,
    userEditUrl,
    onEdit,
}: {
    profile: Profile;
    labels: Labels;
    canEdit: boolean;
    editMessage: string;
    userEditUrl: string;
    onEdit: () => void;
}) {
    const rows: Array<{labelText: string; value: string; wide?: boolean}> = [
        {labelText: label(labels, "email"), value: profile.email},
        {labelText: label(labels, "emaildisplay"), value: profile.maildisplay},
        {labelText: label(labels, "phone1"), value: profile.phone1},
        {labelText: label(labels, "phone2"), value: profile.phone2},
        {labelText: label(labels, "address"), value: profile.address},
        {labelText: label(labels, "city"), value: profile.city},
        {labelText: label(labels, "country"), value: profile.country},
        {labelText: label(labels, "timezone"), value: profile.timezone},
        {labelText: label(labels, "language"), value: profile.language},
        {labelText: label(labels, "department"), value: profile.department},
        {labelText: label(labels, "institution"), value: profile.institution},
        {labelText: label(labels, "idnumber"), value: profile.idnumber},
        {labelText: label(labels, "description"), value: profile.description, wide: true},
        {labelText: label(labels, "interests"), value: (profile.interests || []).join(", "), wide: true},
    ];

    return (
        <div className={mcClasses("mc-card")}>
            <div className={mcClasses("mc-card-header")}>
                <div>
                    <h2>{label(labels, "profiledetails")}</h2>
                    <p>{label(labels, "profiledetailsdesc")}</p>
                </div>
                <div className={mcClasses("mc-profile-detail-actions")}>
                    <button
                        aria-controls="mc-profile-edit-panel"
                        aria-expanded="false"
                        className={mcClasses("mc-button btn-mc-primary")}
                        disabled={!canEdit}
                        title={canEdit ? label(labels, "showprofileedit") : editMessage}
                        type="button"
                        onClick={onEdit}
                    >
                        <i className="bi bi-pencil-square" aria-hidden="true" />
                        {label(labels, "showprofileedit")}
                    </button>
                    {userEditUrl && (
                        <a className={mcClasses("mc-button btn-mc-secondary")} href={userEditUrl}>
                            <i className="bi bi-box-arrow-up-right" aria-hidden="true" />
                            {label(labels, "openfullprofileeditor")}
                        </a>
                    )}
                </div>
            </div>
            <div className={mcClasses("mc-card-body")}>
                <div className={mcClasses("mc-profile-detail-grid")}>
                    {rows
                        .filter((row) => row.value && row.value !== label(labels, "notprovided"))
                        .map((row) => (
                            <div className={mcClasses("mc-profile-detail", row.wide && "mc-profile-detail--wide")} key={row.labelText}>
                                <span>{row.labelText}</span>
                                <strong>{row.value}</strong>
                            </div>
                        ))}
                    {profile.customfields
                        .filter((field) => field.hasdisplayvalue)
                        .map((field) => (
                            <div className={mcClasses("mc-profile-detail", "mc-profile-detail--wide")} key={field.inputname}>
                                <span>{field.label}</span>
                                <strong dangerouslySetInnerHTML={{__html: field.displayvalue}} />
                            </div>
                        ))}
                </div>
            </div>
        </div>
    );
}

function CustomFieldInput({
    field,
    value,
    error,
    onChange,
}: {
    field: EditableCustomField;
    value: string;
    error?: string;
    onChange: Dispatch<string>;
}) {
    const id = `mc-profile-custom-${field.inputname}`;

    const errorId = `${id}-error`;

    if (field.istextarea) {
        return (
            <div className="mb-3">
                <label className={mcClasses("mc-field-label")} htmlFor={id}>
                    {field.label}
                    {field.required && <RequiredMark />}
                </label>
                <textarea
                    {...fieldState(id, error, field.required)}
                    disabled={field.disabled}
                    id={id}
                    required={field.required}
                    rows={4}
                    value={value}
                    onChange={(event) => onChange(event.currentTarget.value)}
                />
                <FieldErrorText id={errorId} message={error} />
            </div>
        );
    }

    if (field.ismenu) {
        return (
            <div className="mb-3">
                <label className={mcClasses("mc-field-label")} htmlFor={id}>
                    {field.label}
                    {field.required && <RequiredMark />}
                </label>
                <select
                    {...fieldState(id, error, field.required)}
                    className={mcClasses("form-select", error ? "mc-field-invalid" : "")}
                    disabled={field.disabled}
                    id={id}
                    required={field.required}
                    value={value}
                    onChange={(event) => onChange(event.currentTarget.value)}
                >
                    {field.options.map((option) => (
                        <option key={option.value} value={option.value}>{option.label}</option>
                    ))}
                </select>
                <FieldErrorText id={errorId} message={error} />
            </div>
        );
    }

    if (field.ischeckbox) {
        return (
            <div className="form-check mb-3">
                <input
                    aria-describedby={error ? errorId : undefined}
                    aria-invalid={error ? true : undefined}
                    aria-required={field.required ? true : undefined}
                    className={mcClasses("form-check-input", error ? "mc-field-invalid" : "")}
                    disabled={field.disabled}
                    id={id}
                    type="checkbox"
                    checked={value === "1"}
                    onChange={(event) => onChange(event.currentTarget.checked ? "1" : "0")}
                />
                <label className="form-check-label" htmlFor={id}>
                    {field.label}
                    {field.required && <RequiredMark />}
                </label>
                <FieldErrorText id={errorId} message={error} />
            </div>
        );
    }

    return (
        <div className="mb-3">
            <label className={mcClasses("mc-field-label")} htmlFor={id}>
                {field.label}
                {field.required && <RequiredMark />}
            </label>
            <input
                {...fieldState(id, error, field.required)}
                disabled={field.disabled}
                id={id}
                required={field.required}
                type={customFieldInputType(field)}
                value={value}
                onChange={(event) => onChange(event.currentTarget.value)}
            />
            <FieldErrorText id={errorId} message={error} />
        </div>
    );
}

export default function LearnerProfile({
    getMethodName,
    saveMethodName,
    savePictureMethodName,
    labels,
    layout,
    maxImageBytes = 0,
}: LearnerProfileProps) {
    useModernCommerceClassSync();
    const [data, setData] = useState<ProfileResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [imageBusy, setImageBusy] = useState(false);
    const [error, setError] = useState("");
    const [message, setMessage] = useState("");
    const [imageFeedback, setImageFeedback] = useState("");
    const [imageFeedbackType, setImageFeedbackType] = useState<"success" | "danger">("success");
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [imageErrors, setImageErrors] = useState<Record<string, string>>({});
    const [isEditing, setIsEditing] = useState(false);
    const [basicForm, setBasicForm] = useState<BasicFormState>({
        firstname: "",
        lastname: "",
        email: "",
        city: "",
        country: "",
        department: "",
        institution: "",
        phone1: "",
        phone2: "",
        address: "",
        idnumber: "",
        timezone: "99",
        maildisplay: "2",
        lang: "",
        description: "",
    });
    const [savedBasicForm, setSavedBasicForm] = useState<BasicFormState>(basicForm);
    const [customValues, setCustomValues] = useState<Record<string, string>>({});
    const [savedCustomValues, setSavedCustomValues] = useState<Record<string, string>>({});
    const [interests, setInterests] = useState<string[]>([]);
    const [savedInterests, setSavedInterests] = useState<string[]>([]);
    const [interestInput, setInterestInput] = useState("");
    const [selectedImage, setSelectedImage] = useState<File | null>(null);
    const [imagePreview, setImagePreview] = useState("");

    useEffect(() => {
        let cancelled = false;

        callMoodleService<ProfileResponse>(getMethodName, {})
            .then((result) => {
                if (!cancelled) {
                    const nextBasicForm = basicFormStateFromResponse(result, labels);
                    const nextCustomValues = customValuesFromFields(result.editablecustomfields);

                    setData(result);
                    setBasicForm(nextBasicForm);
                    setSavedBasicForm(nextBasicForm);
                    setCustomValues(nextCustomValues);
                    setSavedCustomValues(nextCustomValues);
                    setInterests(result.interests ?? []);
                    setSavedInterests(result.interests ?? []);
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
    }, [getMethodName, labels]);

    const resolvedProfile = useMemo<LearnerLayoutUser | undefined>(() => {
        if (!data) {
            return undefined;
        }
        return {
            ...(layout?.user ?? {}),
            fullname: data.profile.fullname,
            initials: initials(data.profile.fullname),
            avatarurl: imagePreview || data.profileimage,
            email: data.profile.email,
        };
    }, [data, imagePreview, layout?.user]);

    const showEditProfile = () => {
        setFieldErrors({});
        setError("");
        setMessage("");
        setBasicForm(savedBasicForm);
        setCustomValues(savedCustomValues);
        setInterests(savedInterests);
        setInterestInput("");
        setIsEditing(true);
    };

    const cancelEditProfile = () => {
        setBasicForm(savedBasicForm);
        setCustomValues(savedCustomValues);
        setInterests(savedInterests);
        setInterestInput("");
        setFieldErrors({});
        setError("");
        setMessage("");
        setIsEditing(false);
    };

    const addInterest = () => {
        const value = interestInput.replace(/,/g, "").trim();
        if (value === "" || interests.includes(value)) {
            setInterestInput("");
            return;
        }
        setInterests([...interests, value]);
        setInterestInput("");
    };

    // Mirrors what learner_profile_service enforces, so an obvious mistake is
    // caught before a round trip. The server remains authoritative.
    const basicRules: RuleMap<BasicFormState> = {
        firstname: [required(label(labels, "error:required")), maxLength(100, label(labels, "error:maxlength"))],
        lastname: [required(label(labels, "error:required")), maxLength(100, label(labels, "error:maxlength"))],
        email: [required(label(labels, "error:required")), email(label(labels, "error:invalidemail"))],
        phone1: pattern(/^[0-9+()\s-]{0,20}$/, label(labels, "error:invalidphone")),
        phone2: pattern(/^[0-9+()\s-]{0,20}$/, label(labels, "error:invalidphone")),
        city: maxLength(120, label(labels, "error:maxlength")),
        description: maxLength(2000, label(labels, "error:maxlength")),
    };

    const saveProfile = async(event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!data) {
            return;
        }

        const clientErrors = runRules(basicForm, basicRules);
        // Custom fields carry their own required flag from the server.
        (data.editablecustomfields || []).forEach((field) => {
            if (field.required && String(customValues[field.inputname] ?? "").trim() === "") {
                clientErrors[field.inputname] = label(labels, "error:required");
            }
        });

        if (Object.keys(clientErrors).length > 0) {
            setFieldErrors(clientErrors);
            focusFirstError(clientErrors, [
                ...Object.keys(basicRules),
                ...(data.editablecustomfields || []).map((field) => field.inputname),
            ]);
            return;
        }

        setBusy(true);
        setError("");
        setMessage("");
        setFieldErrors({});

        try {
            const pending = interestInput.replace(/,/g, "").trim();
            const interestsToSave = pending !== "" && !interests.includes(pending) ? [...interests, pending] : interests;
            const result = await callMoodleService<SaveProfileResponse>(saveMethodName, {
                userid: data.userid,
                ...basicForm,
                interests: interestsToSave,
                customfields: Object.entries(customValues).map(([name, value]) => ({name, value})),
            });
            toast[result.success ? "success" : "error"](result.message);
            setFieldErrors(errorMap(result.errors || []));
            if (result.success) {
                const nextBasicForm = {...basicForm};
                const nextCustomValues = {...customValues};

                setData({
                    ...data,
                    profile: result.profile,
                    countryoptions: data.countryoptions.map((option) => ({
                        ...option,
                        selected: option.value === nextBasicForm.country,
                    })),
                    editable: {
                        ...data.editable,
                        phone1: nextBasicForm.phone1,
                        phone2: nextBasicForm.phone2,
                        address: nextBasicForm.address,
                        idnumber: nextBasicForm.idnumber,
                        timezone: nextBasicForm.timezone,
                        maildisplay: nextBasicForm.maildisplay,
                        lang: nextBasicForm.lang,
                        description: nextBasicForm.description,
                    },
                    interests: interestsToSave,
                    editablecustomfields: syncEditableCustomFields(data.editablecustomfields, nextCustomValues),
                });
                setSavedBasicForm(nextBasicForm);
                setSavedCustomValues(nextCustomValues);
                setInterests(interestsToSave);
                setSavedInterests(interestsToSave);
                setInterestInput("");
                setIsEditing(false);
            }
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : String(caught));
        } finally {
            setBusy(false);
        }
    };

    const uploadImage = async() => {
        if (!data || !selectedImage) {
            setImageErrors({profileimage: label(labels, "profileimagefilemissing")});
            setImageFeedback("");
            return;
        }

        // Checked before the file is base64-encoded and shipped: an oversized
        // upload used to travel the whole way only to be rejected server side.
        if (!selectedImage.type.startsWith("image/")) {
            setImageErrors({profileimage: label(labels, "error:invalidimagetype")});
            setImageFeedback("");
            return;
        }
        if (maxImageBytes > 0 && selectedImage.size > maxImageBytes) {
            setImageErrors({
                profileimage: fmt(label(labels, "error:imagetoolarge"), formatBytes(maxImageBytes)),
            });
            setImageFeedback("");
            return;
        }

        setImageBusy(true);
        setImageErrors({});
        setMessage("");
        setError("");

        try {
            const imagecontent = await fileToBase64(selectedImage);
            const result = await callMoodleService<SavePictureResponse>(savePictureMethodName, {
                userid: data.userid,
                deletepicture: false,
                filename: selectedImage.name,
                mimetype: selectedImage.type,
                filesize: selectedImage.size,
                imagecontent,
            });
            setMessage(result.message);
            setImageFeedback(result.message);
            setImageFeedbackType(result.success ? "success" : "danger");
            setImageErrors(errorMap(result.errors || []));
            if (result.success) {
                setData({...data, profileimage: result.profileimage});
                setSelectedImage(null);
                setImagePreview("");
            }
        } catch (caught) {
            const caughtmessage = caught instanceof Error ? caught.message : String(caught);
            setError(caughtmessage);
            setImageFeedback(caughtmessage);
            setImageFeedbackType("danger");
        } finally {
            setImageBusy(false);
        }
    };

    const removeImage = async() => {
        if (!data) {
            return;
        }

        setImageBusy(true);
        setImageErrors({});
        setMessage("");
        setError("");

        try {
            const result = await callMoodleService<SavePictureResponse>(savePictureMethodName, {
                userid: data.userid,
                deletepicture: true,
            });
            setMessage(result.message);
            setImageFeedback(result.message);
            setImageFeedbackType(result.success ? "success" : "danger");
            setImageErrors(errorMap(result.errors || []));
            if (result.success) {
                setData({...data, profileimage: result.profileimage});
                setSelectedImage(null);
                setImagePreview("");
            }
        } catch (caught) {
            const caughtmessage = caught instanceof Error ? caught.message : String(caught);
            setError(caughtmessage);
            setImageFeedback(caughtmessage);
            setImageFeedbackType("danger");
        } finally {
            setImageBusy(false);
        }
    };

    const chooseImage = (file: File | null) => {
        setSelectedImage(file);
        setImageErrors({});
        setImageFeedback("");
        if (!file) {
            setImagePreview("");
            return;
        }
        setImagePreview(URL.createObjectURL(file));
    };

    return (
        <ModernLearnerLayout
            activeNav="profile"
            title={label(labels, "myprofile")}
            subtitle={label(labels, "profiledetailsdesc")}
            labels={labels}
            layout={layout}
            profile={resolvedProfile}
            avatarEditor={data ? {
                canEdit: data.canedit,
                editMessage: data.editmessage,
                imageUrl: data.profileimage,
                imagePreview,
                imageBusy,
                imageErrors,
                feedbackMessage: imageFeedback,
                feedbackType: imageFeedbackType,
                onFile: chooseImage,
                onUpload: () => void uploadImage(),
                onRemove: () => void removeImage(),
            } : undefined}
        >
            {loading && <LoadingState labels={labels} />}
            {!loading && error && (
                <div className={mcClasses("mc-alert mc-alert--danger")} role="alert">
                    <i className="bi bi-exclamation-triangle mc-alert__icon" aria-hidden="true" />
                    <div className={mcClasses("mc-alert__body")}>{error}</div>
                </div>
            )}
            {!loading && data && (
                <div className={mcClasses("mc-learner-profile")}>
                    {(message || error) && (
                        <div className={mcClasses(`mc-alert ${error ? "mc-alert--danger" : "mc-alert--success"}`)} role="alert">
                            <i
                                className={`bi ${error ? "bi-exclamation-triangle" : "bi-check-circle"} mc-alert__icon`}
                                aria-hidden="true"
                            />
                            <div className={mcClasses("mc-alert__body")}>{error || message}</div>
                        </div>
                    )}

                    <div className={mcClasses("mc-profile-stage")}>
                        {!isEditing && (
                            <div className={mcClasses("mc-profile-panel mc-profile-panel--details")}>
                                <ProfileDetails
                                    profile={data.profile}
                                    labels={labels}
                                    canEdit={data.canedit}
                                    editMessage={data.editmessage}
                                    userEditUrl={data.userediturl || data.urls.editprofile || data.urls.coreprofile}
                                    onEdit={showEditProfile}
                                />
                            </div>
                        )}

                        {isEditing && (
                            <form
                                className={mcClasses("mc-profile-panel mc-profile-panel--edit mc-card")}
                                id="mc-profile-edit-panel"
                                // Without this the browser's own bubble fires first and the
                                // inline messages never render. Native `required` stays on
                                // the controls as a no-JavaScript fallback.
                                noValidate
                                onSubmit={(event) => void saveProfile(event)}
                            >
                                <div className={mcClasses("mc-card-header")}>
                                    <div>
                                        <h2>{label(labels, "editprofile")}</h2>
                                        <p>{label(labels, "basicdetails")}</p>
                                    </div>
                                    <button
                                        aria-label={label(labels, "hideprofileedit")}
                                        className={mcClasses("mc-button mc-btn-icon mc-profile-edit-toggle")}
                                        disabled={busy}
                                        title={label(labels, "hideprofileedit")}
                                        type="button"
                                        onClick={cancelEditProfile}
                                    >
                                        <i className="bi bi-x-lg" aria-hidden="true" />
                                    </button>
                                </div>
                                <div className={mcClasses("mc-card-body")}>
                                    {!data.canedit && (
                                        <div className={mcClasses("mc-alert mc-alert--info")} role="status">
                                            <i className="bi bi-info-circle mc-alert__icon" aria-hidden="true" />
                                            <div className={mcClasses("mc-alert__body")}>{data.editmessage}</div>
                                        </div>
                                    )}

                                    <div className="row g-3">
                                        <div className="col-md-6">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-firstname">
                                                {label(labels, "firstname")}
                                                <RequiredMark />
                                            </label>
                                            <input
                                                {...fieldState("mc-profile-firstname", fieldErrors.firstname, true)}
                                                disabled={!data.canedit || busy}
                                                id="mc-profile-firstname"
                                                required
                                                type="text"
                                                value={basicForm.firstname}
                                                onChange={(event) => setBasicForm({
                                                    ...basicForm,
                                                    firstname: event.currentTarget.value,
                                                })}
                                            />
                                            <FieldErrorText id="mc-profile-firstname-error" message={fieldErrors.firstname} />
                                        </div>
                                        <div className="col-md-6">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-lastname">
                                                {label(labels, "lastname")}
                                                <RequiredMark />
                                            </label>
                                            <input
                                                {...fieldState("mc-profile-lastname", fieldErrors.lastname, true)}
                                                disabled={!data.canedit || busy}
                                                id="mc-profile-lastname"
                                                required
                                                type="text"
                                                value={basicForm.lastname}
                                                onChange={(event) => setBasicForm({
                                                    ...basicForm,
                                                    lastname: event.currentTarget.value,
                                                })}
                                            />
                                            <FieldErrorText id="mc-profile-lastname-error" message={fieldErrors.lastname} />
                                        </div>
                                        <div className="col-md-6">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-email">
                                                {label(labels, "email")}
                                                <RequiredMark />
                                            </label>
                                            <input
                                                {...fieldState("mc-profile-email", fieldErrors.email, true)}
                                                disabled={!data.canedit || busy}
                                                id="mc-profile-email"
                                                required
                                                type="email"
                                                value={basicForm.email}
                                                onChange={(event) => setBasicForm({
                                                    ...basicForm,
                                                    email: event.currentTarget.value,
                                                })}
                                            />
                                            <FieldErrorText id="mc-profile-email-error" message={fieldErrors.email} />
                                        </div>
                                        <div className="col-md-6">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-country">
                                                {label(labels, "country")}
                                            </label>
                                            <select
                                                className="form-select"
                                                disabled={!data.canedit || busy}
                                                id="mc-profile-country"
                                                value={basicForm.country}
                                                onChange={(event) => setBasicForm({
                                                    ...basicForm,
                                                    country: event.currentTarget.value,
                                                })}
                                            >
                                                {data.countryoptions.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                            <FieldErrorText message={fieldErrors.country} />
                                        </div>
                                        <div className="col-md-4">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-city">
                                                {label(labels, "city")}
                                            </label>
                                            <input
                                                {...fieldState("mc-profile-city", fieldErrors.city)}
                                                disabled={!data.canedit || busy}
                                                id="mc-profile-city"
                                                maxLength={120}
                                                type="text"
                                                value={basicForm.city}
                                                onChange={(event) => setBasicForm({
                                                    ...basicForm,
                                                    city: event.currentTarget.value,
                                                })}
                                            />
                                            <FieldErrorText id="mc-profile-city-error" message={fieldErrors.city} />
                                        </div>
                                        <div className="col-md-4">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-department">
                                                {label(labels, "department")}
                                            </label>
                                            <input
                                                className="form-control"
                                                disabled={!data.canedit || busy}
                                                id="mc-profile-department"
                                                type="text"
                                                value={basicForm.department}
                                                onChange={(event) => setBasicForm({
                                                    ...basicForm,
                                                    department: event.currentTarget.value,
                                                })}
                                            />
                                            <FieldErrorText message={fieldErrors.department} />
                                        </div>
                                        <div className="col-md-4">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-institution">
                                                {label(labels, "institution")}
                                            </label>
                                            <input
                                                className="form-control"
                                                disabled={!data.canedit || busy}
                                                id="mc-profile-institution"
                                                type="text"
                                                value={basicForm.institution}
                                                onChange={(event) => setBasicForm({
                                                    ...basicForm,
                                                    institution: event.currentTarget.value,
                                                })}
                                            />
                                            <FieldErrorText message={fieldErrors.institution} />
                                        </div>
                                    </div>

                                    <hr />
                                    <h3 className="h6">{label(labels, "contactdetails")}</h3>
                                    <div className="row g-3">
                                        <div className="col-md-4">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-phone1">
                                                {label(labels, "phone1")}
                                            </label>
                                            <input
                                                {...fieldState("mc-profile-phone1", fieldErrors.phone1)}
                                                disabled={!data.canedit || busy || data.fieldlocks.phone1}
                                                id="mc-profile-phone1"
                                                type="text"
                                                value={basicForm.phone1}
                                                onChange={(event) => setBasicForm({...basicForm, phone1: event.currentTarget.value})}
                                            />
                                            <FieldErrorText id="mc-profile-phone1-error" message={fieldErrors.phone1} />
                                        </div>
                                        <div className="col-md-4">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-phone2">
                                                {label(labels, "phone2")}
                                            </label>
                                            <input
                                                {...fieldState("mc-profile-phone2", fieldErrors.phone2)}
                                                disabled={!data.canedit || busy || data.fieldlocks.phone2}
                                                id="mc-profile-phone2"
                                                type="text"
                                                value={basicForm.phone2}
                                                onChange={(event) => setBasicForm({...basicForm, phone2: event.currentTarget.value})}
                                            />
                                            <FieldErrorText id="mc-profile-phone2-error" message={fieldErrors.phone2} />
                                        </div>
                                        <div className="col-md-4">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-address">
                                                {label(labels, "address")}
                                            </label>
                                            <input
                                                className="form-control"
                                                disabled={!data.canedit || busy || data.fieldlocks.address}
                                                id="mc-profile-address"
                                                type="text"
                                                value={basicForm.address}
                                                onChange={(event) => setBasicForm({...basicForm, address: event.currentTarget.value})}
                                            />
                                            <FieldErrorText message={fieldErrors.address} />
                                        </div>
                                    </div>

                                    <hr />
                                    <h3 className="h6">{label(labels, "aboutyou")}</h3>
                                    <div className="mb-3">
                                        <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-description">
                                            {label(labels, "description")}
                                        </label>
                                        <textarea
                                            {...fieldState("mc-profile-description", fieldErrors.description)}
                                            disabled={!data.canedit || busy || data.fieldlocks.description}
                                            id="mc-profile-description"
                                            maxLength={2000}
                                            rows={4}
                                            value={basicForm.description}
                                            onChange={(event) => setBasicForm({...basicForm, description: event.currentTarget.value})}
                                        />
                                        <FieldErrorText id="mc-profile-description-error" message={fieldErrors.description} />
                                    </div>
                                    <div className="mb-3">
                                        <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-interests">
                                            {label(labels, "interests")}
                                        </label>
                                        {interests.length > 0 && (
                                            <div className={mcClasses("mc-profile-interests")}>
                                                {interests.map((tag, index) => (
                                                    <span className={mcClasses("mc-profile-interest-chip")} key={`${tag}-${index}`}>
                                                        {tag}
                                                        <button
                                                            aria-label={label(labels, "remove")}
                                                            className={mcClasses("mc-button mc-profile-interest-remove")}
                                                            data-mc-button="ghost"
                                                            data-mc-button-size="icon"
                                                            disabled={busy}
                                                            type="button"
                                                            onClick={() => setInterests(interests.filter((_, itemindex) => itemindex !== index))}
                                                        >
                                                            <i className="bi bi-x" aria-hidden="true" />
                                                        </button>
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                        <input
                                            className="form-control"
                                            disabled={busy}
                                            id="mc-profile-interests"
                                            placeholder={label(labels, "interestsplaceholder")}
                                            type="text"
                                            value={interestInput}
                                            onChange={(event) => setInterestInput(event.currentTarget.value)}
                                            onKeyDown={(event) => {
                                                if (event.key === "Enter" || event.key === ",") {
                                                    event.preventDefault();
                                                    addInterest();
                                                }
                                            }}
                                            onBlur={addInterest}
                                        />
                                    </div>

                                    <hr />
                                    <h3 className="h6">{label(labels, "preferences")}</h3>
                                    <div className="row g-3">
                                        <div className="col-12 col-sm-6 col-lg-3">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-timezone">
                                                {label(labels, "timezone")}
                                            </label>
                                            <select
                                                className="form-select"
                                                disabled={!data.canedit || busy || data.fieldlocks.timezone}
                                                id="mc-profile-timezone"
                                                value={basicForm.timezone}
                                                onChange={(event) => setBasicForm({...basicForm, timezone: event.currentTarget.value})}
                                            >
                                                {data.timezoneoptions.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                            <FieldErrorText message={fieldErrors.timezone} />
                                        </div>
                                        <div className="col-12 col-sm-6 col-lg-3">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-maildisplay">
                                                {label(labels, "emaildisplay")}
                                            </label>
                                            <select
                                                className="form-select"
                                                disabled={!data.canedit || busy || data.fieldlocks.maildisplay}
                                                id="mc-profile-maildisplay"
                                                value={basicForm.maildisplay}
                                                onChange={(event) => setBasicForm({...basicForm, maildisplay: event.currentTarget.value})}
                                            >
                                                {data.maildisplayoptions.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                            <FieldErrorText message={fieldErrors.maildisplay} />
                                        </div>
                                        <div className="col-12 col-sm-6 col-lg-3">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-lang">
                                                {label(labels, "language")}
                                            </label>
                                            <select
                                                className="form-select"
                                                disabled={!data.canedit || busy || data.fieldlocks.lang}
                                                id="mc-profile-lang"
                                                value={basicForm.lang}
                                                onChange={(event) => setBasicForm({...basicForm, lang: event.currentTarget.value})}
                                            >
                                                {data.languageoptions.map((option) => (
                                                    <option key={option.value} value={option.value}>{option.label}</option>
                                                ))}
                                            </select>
                                            <FieldErrorText message={fieldErrors.lang} />
                                        </div>
                                        <div className="col-12 col-sm-6 col-lg-3">
                                            <label className={mcClasses("mc-field-label")} htmlFor="mc-profile-idnumber">
                                                {label(labels, "idnumber")}
                                            </label>
                                            <input
                                                className="form-control"
                                                disabled={!data.canedit || busy || data.fieldlocks.idnumber}
                                                id="mc-profile-idnumber"
                                                type="text"
                                                value={basicForm.idnumber}
                                                onChange={(event) => setBasicForm({...basicForm, idnumber: event.currentTarget.value})}
                                            />
                                            <FieldErrorText message={fieldErrors.idnumber} />
                                        </div>
                                    </div>

                                    {data.editablecustomfields.length > 0 && (
                                        <>
                                            <hr />
                                            <h3 className="h6">
                                                {label(labels, "customprofilefields")}
                                            </h3>
                                            <div className="row g-3">
                                                {data.editablecustomfields.map((field) => (
                                                    <div className="col-md-6" key={field.inputname}>
                                                        <CustomFieldInput
                                                            field={field}
                                                            value={customValues[field.inputname] ?? ""}
                                                            error={fieldErrors[field.inputname]}
                                                            onChange={(value) => setCustomValues({
                                                                ...customValues,
                                                                [field.inputname]: value,
                                                            })}
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                        </>
                                    )}
                                </div>
                                <div className={mcClasses("mc-card-footer d-flex flex-wrap justify-content-end gap-2")}>
                                    <button
                                        className={mcClasses("mc-button btn-mc-secondary")}
                                        disabled={busy}
                                        type="button"
                                        onClick={cancelEditProfile}
                                    >
                                        {label(labels, "cancel")}
                                    </button>
                                    <McButton className={mcClasses("btn-mc-primary")} disabled={!data.canedit} loading={busy} loadingLabel={label(labels, "saving", "Saving...")} type="submit">
                                        {label(labels, "savechanges")}
                                    </McButton>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </ModernLearnerLayout>
    );
}
