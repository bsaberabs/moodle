import{useEffect as n}from"react";var r=()=>{},c=(...s)=>{let o=new Set;return s.forEach(i=>{i&&i.split(/\s+/).filter(Boolean).forEach(e=>{o.add(e)})}),Array.from(o).join(" ")},p=(s,o)=>s?o==="ASC"?"bi bi-caret-up-fill":"bi bi-caret-down-fill":"bi bi-chevron-expand",d=()=>{n(()=>{r()},[])},t=s=>{let o=window.require;typeof o=="function"&&o(["local_moderncommerce/floating_notifications"],i=>s(i))},l={success:(s,o)=>t(i=>i.success(s,o)),error:(s,o)=>t(i=>i.error(s,o)),warning:(s,o)=>t(i=>i.warning(s,o)),info:(s,o)=>t(i=>i.info(s,o))};export{c as mcClasses,p as sortIconClass,r as syncModernCommerceClasses,l as toast,d as useModernCommerceClassSync};
/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
