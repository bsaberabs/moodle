import{useEffect as g}from"react";var r=(...n)=>{let o=new Set;return n.forEach(t=>{t&&t.split(/\s+/).filter(Boolean).forEach(e=>{o.add(e)})}),Array.from(o).join(" ")};import{jsx as b}from"react/jsx-runtime";function M({variant:n,size:o,loading:t=!1,loadingLabel:e,buttonState:a,block:c=!1,className:u,children:p,disabled:d,type:l="button","aria-busy":s,...m}){let i=t||s===!0||s==="true";return b("button",{...m,"aria-busy":i?"true":s,className:r("mc-button",c&&"mc-button--block",u),"data-mc-button":n,"data-mc-button-size":o,"data-mc-button-state":i?"loading":a,disabled:d||t,type:l,children:t&&e!==void 0?e:p})}export{M as McButton};
/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Reusable Modern Commerce button primitive for React screens.
 *
 * @module     local_moderncommerce/button
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
