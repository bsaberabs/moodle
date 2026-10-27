import{useEffect as w}from"react";var n=(...i)=>{let t=new Set;return i.forEach(o=>{o&&o.split(/\s+/).filter(Boolean).forEach(s=>{t.add(s)})}),Array.from(t).join(" ")};import{jsx as e,jsxs as p}from"react/jsx-runtime";var d=120,m=68;function h({thumbnail:i,thumbnailHref:t,thumbnailAlt:o}){let s=i?e("img",{src:i,alt:o??"",width:d,height:m,className:"rounded object-fit-cover flex-shrink-0",loading:"lazy"}):e("span",{className:"rounded bg-light flex-shrink-0",style:{width:d,height:m}});return t?e("a",{href:t,className:"flex-shrink-0 d-block",children:s}):s}function v({thumbnail:i,thumbnailHref:t,thumbnailAlt:o,meta:s,title:r,titleHref:a,titleAs:f="h2",subtitle:l,body:g,actions:c}){let u=f,b=a?e("a",{className:"text-decoration-none text-reset",href:a,children:r}):r;return e("article",{className:n("mc-card mb-2 mc-learner-list-row"),children:p("div",{className:n("mc-card-body d-flex gap-3 align-items-start flex-wrap flex-md-nowrap"),children:[e(h,{thumbnail:i,thumbnailHref:t,thumbnailAlt:o}),p("div",{className:"flex-grow-1 min-w-0",children:[s&&e("div",{className:"d-flex flex-wrap align-items-center gap-2 mb-1",children:s}),e(u,{className:n("mc-card-title mb-1"),children:b}),l&&e("p",{className:n("mc-cell-muted mb-0"),children:l}),g]}),c&&e("div",{className:"d-flex flex-column gap-2 align-items-md-end",children:c})]})})}export{v as default};
/**
 * Runtime design-system helpers for Modern Commerce React surfaces.
 *
 * @module     local_moderncommerce/design_system
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Shared learner "list row" card for Modern Commerce.
 *
 * One central, editable component for the horizontal media-row layout used by
 * learner list views (catalogue/library list mode, dashboard "Continue learning"
 * list mode, and any future learner list). Layout: thumbnail on the left, a
 * content column in the middle (meta line, title, subtitle, body), and a
 * right-aligned actions column. Every slot is a ReactNode, so each page composes
 * its own content while sharing one layout/markup that can be restyled here.
 *
 * @module     local_moderncommerce/learner_list_row
 * @copyright  2026 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
