var l=e=>new Intl.NumberFormat(document.documentElement.lang||void 0).format(e);var c=e=>Math.min(100,Math.max(0,e));import{useEffect as g}from"react";var t=(...e)=>{let r=new Set;return e.forEach(o=>{o&&o.split(/\s+/).filter(Boolean).forEach(i=>{r.add(i)})}),Array.from(r).join(" ")};import{jsx as n,jsxs as s}from"react/jsx-runtime";var a=(e,r,o="")=>e[r]||o||r,u=(e,r)=>e.completed?a(r,"reviewcourse","Review course"):c(e.progress)>0?a(r,"resumecourse","Resume course"):a(r,"startcourse","Start course");function m({course:e,labels:r}){let o=c(e.progress),i=u(e,r);return s("div",{className:t("mc-learner-course-card"),role:"listitem",children:[e.hasimage?n("img",{src:e.imageurl,alt:"",className:t("mc-learner-course-card__thumb"),width:72,height:54,loading:"lazy"}):n("span",{className:t("mc-learner-course-card__thumb d-flex align-items-center justify-content-center"),children:n("i",{className:"bi bi-play-circle text-muted","aria-hidden":"true"})}),s("div",{className:t("mc-learner-course-card__body"),children:[n("a",{className:t("mc-learner-course-card__title mc-learner-course-card__link"),href:e.courseurl,children:e.name}),s("div",{className:t("mc-learner-course-card__meta d-flex flex-wrap gap-2"),children:[e.categoryname&&n("span",{children:e.categoryname}),e.modulecount>0&&s("span",{children:[l(e.modulecount)," ",a(r,"includedcourses")]}),e.sourcelabel&&n("span",{children:e.sourcelabel})]})]}),s("div",{className:t("mc-learner-course-card__aside"),children:[s("div",{className:t("mc-learner-course-card__progress"),role:"progressbar","aria-valuenow":o,"aria-valuemin":0,"aria-valuemax":100,"aria-label":a(r,"progress","Progress"),children:[n("span",{className:t("mc-progress-bar-wrap"),"aria-hidden":"true",children:n("span",{className:t("mc-progress-bar-fill",e.completed&&"mc-progress-bar-fill--complete"),style:{width:`${o}%`}})}),n("span",{className:t("mc-learner-course-card__percent"),children:e.progresslabel})]}),n("span",{className:t("mc-learner-course-card__seen"),children:e.lastaccesslabel?`${a(r,"lastopened","Last opened")}: ${e.lastaccesslabel}`:""}),s("a",{className:t("mc-button btn-mc-secondary py-1 px-3 mc-learner-course-card__action"),href:e.courseurl,children:[i,s("span",{className:"visually-hidden",children:[" \u2014 ",e.name]})]})]})]})}export{u as courseRowActionLabel,m as default};
/**
 * Shared learner React helpers for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_common
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Shared learner course row for Modern Commerce.
 *
 * The dense, column-aligned row used wherever a learner sees a list of their own
 * courses: My courses, and the dashboard's "Continue learning" list view.
 *
 * Layout is a three-column grid — thumbnail, title + meta, then a right-hand aside
 * holding progress, last-opened and the action. The aside columns are fixed width so
 * the progress tracks share one x-origin down the list and can be compared at a glance.
 *
 * The row is a div, not an anchor: only the title links, stretched over the row via
 * ::after. That keeps the accessible name to the course title rather than the
 * concatenation of every field, and leaves the row free to hold a real action button.
 *
 * @module     local_moderncommerce/learner_course_row
 * @copyright  2026 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
