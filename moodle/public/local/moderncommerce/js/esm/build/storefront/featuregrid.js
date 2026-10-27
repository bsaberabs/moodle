import{jsx as t,jsxs as a}from"react/jsx-runtime";var m=["topbar","plain","tile","bordered"],g=e=>e&&m.includes(e)?e:"topbar",d=e=>e===4?"col-12 col-sm-6 col-lg-3":"col-12 col-sm-6 col-lg-4",f=e=>(e||"star").replace(/^bi\s+/,"").replace(/^bi-/,"");function u({card:e,treatment:c,centred:r}){let s={};e.accentcolor&&(s["--mc-fg-card-accent"]=e.accentcolor),e.iconcolor&&(s["--mc-fg-card-icon"]=e.iconcolor);let n=["mc-fg__card",`mc-fg__card--${c}`,r?"mc-fg__card--centred":"","h-100"].filter(Boolean).join(" ");return a("div",{className:n,style:s,children:[c==="topbar"&&t("span",{className:"mc-fg__topbar","aria-hidden":"true"}),t("span",{className:"mc-fg__icon-wrap",children:t("i",{className:`mc-fg__icon bi bi-${f(e.icon)}`,"aria-hidden":"true"})}),e.title&&t("h3",{className:"mc-fg__card-title",children:e.title}),e.text&&t("p",{className:"mc-fg__card-text",children:e.text}),e.linklabel&&e.linkurl&&t("a",{className:"mc-fg__card-link",href:e.linkurl,children:e.linklabel})]})}function _(e){let c=g(e.style),r=e.columns===4?4:3,s=e.centercards!==!1,n=(e.cards||[]).slice(0,12);if(!n.length)return null;let i=e.showheader!==!1&&!!(e.title||e.subtitle);return t("section",{className:`mc-fg mc-fg--${c}`,style:e.cssvars,children:a("div",{className:"mc-fg__inner container",children:[i&&a("header",{className:"mc-fg__header",children:[e.title&&t("h2",{className:"mc-fg__title",children:e.title}),e.subtitle&&t("p",{className:"mc-fg__subtitle",children:e.subtitle})]}),t("div",{className:"row g-4",children:n.map((l,o)=>t("div",{className:d(r),children:t(u,{card:l,treatment:c,centred:s})},o))})]})})}export{_ as default};
/**
 * Feature card grid for the Modern Commerce storefront.
 *
 * Up to twelve icon cards laid out on the Bootstrap grid, 3 or 4 per row on
 * desktop as the admin chooses, collapsing to 2-up on tablet and 1-up on phones.
 * Each card carries its own icon and accent colour so a site can run one hue per
 * card without any extra markup.
 *
 * @module     local_moderncommerce/storefront/featuregrid
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
