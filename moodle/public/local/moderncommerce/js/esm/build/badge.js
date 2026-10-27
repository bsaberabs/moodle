import{useEffect as l}from"react";var t=(...n)=>{let e=new Set;return n.forEach(o=>{o&&o.split(/\s+/).filter(Boolean).forEach(s=>{e.add(s)})}),Array.from(e).join(" ")};import{jsx as a,jsxs as p}from"react/jsx-runtime";function f({children:n,variant:e="neutral",tone:o="soft",size:s="md",icon:i,dot:r=!1,className:c,title:d}){return p("span",{className:t("mc-badge",`mc-badge--${e}`,`mc-badge--tone-${o}`,s!=="md"&&`mc-badge--${s}`,c),title:d,children:[r&&a("span",{className:t("mc-badge__dot"),"aria-hidden":"true"}),i&&a("i",{className:t("bi",i,"mc-badge__icon"),"aria-hidden":"true"}),a("span",{className:t("mc-badge__label"),children:n})]})}function b({children:n,inline:e=!1,stacked:o=!1,className:s}){return a("span",{className:t("mc-badge-group",e&&"mc-badge-group--inline",o&&"mc-badge-group--stacked",s),children:n})}export{f as McBadge,b as McBadgeGroup};
/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Shared Modern Commerce badge primitives.
 *
 * @module     local_moderncommerce/badge
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
