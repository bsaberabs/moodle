var m=t=>new Intl.NumberFormat(document.documentElement.lang||void 0).format(t);var d=t=>Math.min(100,Math.max(0,t));import{useEffect as h}from"react";var r=(...t)=>{let n=new Set;return t.forEach(o=>{o&&o.split(/\s+/).filter(Boolean).forEach(s=>{n.add(s)})}),Array.from(n).join(" ")};import{Fragment as y,jsx as e,jsxs as p}from"react/jsx-runtime";var g=t=>typeof t=="number"?m(t):t;function S({children:t,className:n}){return e("div",{className:r("mc-stat-strip mc-learner-stat-strip",n),role:"list",children:t})}function _({label:t,value:n,icon:o,variant:s="primary",progress:a,href:i,featured:f=!1}){let c=typeof a=="number"?d(a):null,l=r("mc-stat-tile","mc-learner-stat-tile",`mc-stat-tile--${s}`,i&&"mc-learner-stat-tile--link",f&&"mc-learner-stat-tile--featured"),u=p(y,{children:[e("i",{className:`bi ${o} mc-stat-tile__icon`,"aria-hidden":"true"}),p("div",{className:r("mc-stat-tile__body"),children:[e("span",{className:r("mc-stat-tile__label"),children:t}),e("strong",{className:r("mc-stat-tile__value"),children:g(n)}),c!==null&&e("span",{className:r("mc-learner-stat-tile__progress"),"aria-hidden":"true",children:e("span",{style:{width:`${c}%`}})})]}),e("i",{className:`bi ${o} mc-stat-tile__watermark`,"aria-hidden":"true"})]});return i?e("a",{className:l,href:i,role:"listitem",children:u}):e("article",{className:l,role:"listitem",children:u})}export{S as LearnerStatStrip,_ as LearnerStatTile};
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
 * Shared learner statistic tiles for Modern Commerce.
 *
 * @module     local_moderncommerce/learner_stat_tiles
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
