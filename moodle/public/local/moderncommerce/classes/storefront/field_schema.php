<?php
// This file is part of Moodle and is licensed under the
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Declarative settings field schema for each storefront widget type.
 *
 * These descriptors mirror the editable fields of the former local_modernwidgets
 * moodleforms (slider_form, featured_form, categories_form, trustbadges_form,
 * countdown_form, testimonials_form, instructors_form, newsletter_form) plus the
 * Modern Commerce catalog grid. They drive the React widget settings editors that
 * replaced those server-rendered forms.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

/**
 * Provides the editable settings field definitions for each widget type.
 */
class field_schema {
    /**
     * Editable settings fields for a widget type.
     *
     * Each field is an associative array:
     *   - name:    settings key
     *   - label:   human-readable label (plain English fallback)
     *   - type:    one of text|textarea|number|checkbox|select|color|url|list
     *   - default: default value
     *   - choices: ['value' => 'label', ...] (select only)
     *   - fields:  subfield definitions (list only)
     *   - showwhen: optional visibility rule(s), e.g. ['field' => 'layout', 'equals' => 'card']
     *
     * @param string $type Widget type key.
     * @return array List of field descriptors.
     */
    public static function for_type(string $type): array {
        $schemas = [
            'slider' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                [
                    'name' => 'design',
                    'label' => 'Design',
                    'type' => 'select',
                    'default' => 'overlay',
                    'choices' => [
                        'overlay' => 'Overlay',
                        'split' => 'Split',
                        'card' => 'Card',
                    ],
                ],
                ['name' => 'autoplay', 'label' => 'Autoplay', 'type' => 'checkbox', 'default' => true],
                ['name' => 'interval', 'label' => 'Interval (ms)', 'type' => 'number', 'default' => 6000,
                    'showwhen' => ['field' => 'autoplay', 'truthy' => true]],
                ['name' => 'showarrows', 'label' => 'Show arrows', 'type' => 'checkbox', 'default' => true],
                ['name' => 'showdots', 'label' => 'Show dots', 'type' => 'checkbox', 'default' => true],
                ['name' => 'buttoncolor', 'label' => 'Button background', 'type' => 'color', 'default' => ''],
                ['name' => 'buttontextcolor', 'label' => 'Button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttonfontsize', 'label' => 'Button font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'buttonradius', 'label' => 'Button radius (px)', 'type' => 'number', 'default' => 0],
            ],
            'featured' => self::product_list_fields(),
            'related' => self::product_list_fields(),
            'categories' => [
                [
                    'name' => 'style',
                    'label' => 'Style',
                    'type' => 'select',
                    'default' => 'minimal',
                    'choices' => [
                        'minimal' => 'Minimal (current)',
                        'colourful' => 'Colourful',
                        'carousel' => 'Carousel',
                    ],
                ],
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => ''],
                ['name' => 'showcount', 'label' => 'Show course count', 'type' => 'checkbox', 'default' => true],
                [
                    'name' => 'visiblecards',
                    'label' => 'Categories visible per view',
                    'type' => 'number',
                    'default' => 4,
                    'showwhen' => ['field' => 'style', 'equals' => 'carousel'],
                ],
                ['name' => 'bgcolor', 'label' => 'Section background colour', 'type' => 'color', 'default' => ''],
                [
                    'name' => 'items',
                    'label' => 'Categories',
                    'type' => 'list',
                    'default' => [],
                    'fields' => [
                        [
                            'name' => 'categoryid',
                            'label' => 'Category',
                            'type' => 'select',
                            'default' => '',
                            'choices' => self::category_choices(),
                        ],
                        ['name' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'default' => 'collection'],
                        ['name' => 'color', 'label' => 'Tile colour (optional)', 'type' => 'color', 'default' => '',
                            'showwhen' => ['field' => 'style', 'equals' => ['colourful', 'carousel']]],
                    ],
                ],
                ['name' => 'iconcolor', 'label' => 'Icon colour', 'type' => 'color', 'default' => '',
                    'showwhen' => ['field' => 'style', 'equals' => 'minimal']],
                ['name' => 'titlecolor', 'label' => 'Title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Title font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subtitle colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subtitle font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'cardbgcolor', 'label' => 'Card background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardtextcolor', 'label' => 'Card text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardtextfontsize', 'label' => 'Card text font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'cardradius', 'label' => 'Card radius (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'iconbgcolor', 'label' => 'Icon background', 'type' => 'color', 'default' => ''],
                ['name' => 'iconsize', 'label' => 'Icon size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'countcolor', 'label' => 'Count text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'countfontsize', 'label' => 'Count font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
            ],
            'trustbadges' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Why learners trust us'],
                [
                    'name' => 'badges',
                    'label' => 'Badges',
                    'type' => 'list',
                    'default' => [],
                    'fields' => [
                        [
                            'name' => 'icon',
                            'label' => 'Icon',
                            'type' => 'icon',
                            'default' => 'check-circle',
                            'choices' => [
                                'shield-check' => 'Secure checkout',
                                'lock' => 'Lock',
                                'lightning-charge' => 'Instant access',
                                'patch-check' => 'Certificate',
                                'award' => 'Award',
                                'arrow-counterclockwise' => 'Guarantee',
                                'credit-card' => 'Payment',
                                'mortarboard' => 'Learning',
                                'people' => 'Community',
                                'headset' => 'Support',
                                'star' => 'Star',
                                'check-circle' => 'Check',
                            ],
                        ],
                        ['name' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => ''],
                        ['name' => 'sublabel', 'label' => 'Sublabel', 'type' => 'text', 'default' => ''],
                    ],
                ],
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Title font size (px)', 'type' => 'number', 'default' => 24],
                ['name' => 'cardbgcolor', 'label' => 'Card background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Card border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Card border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'cardradius', 'label' => 'Card radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'iconbgcolor', 'label' => 'Icon background', 'type' => 'color', 'default' => ''],
                ['name' => 'iconcolor', 'label' => 'Icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'iconsize', 'label' => 'Icon size (px)', 'type' => 'number', 'default' => 26],
                ['name' => 'labelcolor', 'label' => 'Label colour', 'type' => 'color', 'default' => ''],
                ['name' => 'labelfontsize', 'label' => 'Label font size (px)', 'type' => 'number', 'default' => 16],
                ['name' => 'sublabelcolor', 'label' => 'Sublabel colour', 'type' => 'color', 'default' => ''],
                ['name' => 'sublabelfontsize', 'label' => 'Sublabel font size (px)', 'type' => 'number', 'default' => 14],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
            ],
            'partners' => [
                [
                    'name' => 'style',
                    'label' => 'Style',
                    'type' => 'select',
                    'default' => 'grid',
                    'choices' => [
                        'grid' => 'Grid',
                        'carousel' => 'Carousel',
                        'marquee' => 'Marquee',
                    ],
                ],
                ['name' => 'heading', 'label' => 'Heading', 'type' => 'text',
                    'default' => 'Trusted Technical Partners'],
                [
                    'name' => 'logos',
                    'label' => 'Logos',
                    'type' => 'list',
                    'default' => [],
                    'fields' => [
                        ['name' => 'image', 'label' => 'Logo image', 'type' => 'image', 'default' => ''],
                        ['name' => 'name', 'label' => 'Company name', 'type' => 'text', 'default' => ''],
                        ['name' => 'linkurl', 'label' => 'Link URL', 'type' => 'url', 'default' => ''],
                    ],
                ],
                ['name' => 'visiblelogos', 'label' => 'Logos visible per view', 'type' => 'number', 'default' => 5,
                    'showwhen' => ['field' => 'style', 'equals' => ['grid', 'carousel']]],
                ['name' => 'speed', 'label' => 'Scroll speed (seconds)', 'type' => 'number', 'default' => 30,
                    'showwhen' => ['field' => 'style', 'equals' => 'marquee']],
                ['name' => 'pauseonhover', 'label' => 'Pause on hover', 'type' => 'checkbox', 'default' => true,
                    'showwhen' => ['field' => 'style', 'equals' => 'marquee']],
                ['name' => 'greyscale', 'label' => 'Greyscale logos', 'type' => 'checkbox', 'default' => false],
                ['name' => 'logoheight', 'label' => 'Logo height (px)', 'type' => 'number', 'default' => 48],
                ['name' => 'logogap', 'label' => 'Gap between logos (px)', 'type' => 'number', 'default' => 48],
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'headingcolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'headingfontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 28],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
            ],
            'countdown' => [
                ['name' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => ''],
                ['name' => 'endtime', 'label' => 'End time', 'type' => 'datetime', 'default' => 0],
                ['name' => 'expiredmessage', 'label' => 'Expired message', 'type' => 'text', 'default' => ''],
                ['name' => 'ctalabel', 'label' => 'CTA label', 'type' => 'text', 'default' => ''],
                ['name' => 'ctaurl', 'label' => 'CTA URL', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'ctalabel', 'truthy' => true]],
                ['name' => 'bgcolor', 'label' => 'Bar background colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textcolor', 'label' => 'Text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'headingcolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'headingfontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'timerbgcolor', 'label' => 'Timer box background', 'type' => 'color', 'default' => ''],
                ['name' => 'timernumbercolor', 'label' => 'Timer number colour', 'type' => 'color', 'default' => ''],
                ['name' => 'timernumberfontsize', 'label' => 'Timer number font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'timerlabelcolor', 'label' => 'Timer label colour', 'type' => 'color', 'default' => ''],
                ['name' => 'timerlabelfontsize', 'label' => 'Timer label font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'buttoncolor', 'label' => 'Button background', 'type' => 'color', 'default' => ''],
                ['name' => 'buttontextcolor', 'label' => 'Button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'expiredbgcolor', 'label' => 'Expired background', 'type' => 'color', 'default' => ''],
                ['name' => 'expiredtextcolor', 'label' => 'Expired text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
            ],
            'testimonials' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => ''],
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Title font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subtitle colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subtitle font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'cardbgcolor', 'label' => 'Card background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Card border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Card border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'cardradius', 'label' => 'Card radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'ratingcolor', 'label' => 'Rating star colour', 'type' => 'color', 'default' => ''],
                ['name' => 'quotecolor', 'label' => 'Quote colour', 'type' => 'color', 'default' => ''],
                ['name' => 'quotefontsize', 'label' => 'Quote font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'avatarbgcolor', 'label' => 'Avatar background', 'type' => 'color', 'default' => ''],
                ['name' => 'avatarcolor', 'label' => 'Avatar text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'namecolor', 'label' => 'Author colour', 'type' => 'color', 'default' => ''],
                ['name' => 'namefontsize', 'label' => 'Author font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'rolecolor', 'label' => 'Role colour', 'type' => 'color', 'default' => ''],
                ['name' => 'rolefontsize', 'label' => 'Role font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                [
                    'name' => 'testimonials',
                    'label' => 'Testimonials',
                    'type' => 'list',
                    'default' => [],
                    'fields' => [
                        ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'default' => ''],
                        ['name' => 'author', 'label' => 'Author', 'type' => 'text', 'default' => ''],
                        ['name' => 'role', 'label' => 'Role', 'type' => 'text', 'default' => ''],
                        ['name' => 'rating', 'label' => 'Rating', 'type' => 'number', 'default' => 5],
                    ],
                ],
            ],
            'instructors' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => ''],
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Title font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subtitle colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subtitle font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'cardbgcolor', 'label' => 'Card background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Card border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Card border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'cardradius', 'label' => 'Card radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'avatarbgcolor', 'label' => 'Avatar background', 'type' => 'color', 'default' => ''],
                ['name' => 'avatarcolor', 'label' => 'Avatar text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'namecolor', 'label' => 'Name colour', 'type' => 'color', 'default' => ''],
                ['name' => 'namefontsize', 'label' => 'Name font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'rolecolor', 'label' => 'Role colour', 'type' => 'color', 'default' => ''],
                ['name' => 'rolefontsize', 'label' => 'Role font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'biocolor', 'label' => 'Bio colour', 'type' => 'color', 'default' => ''],
                ['name' => 'biofontsize', 'label' => 'Bio font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                [
                    'name' => 'instructors',
                    'label' => 'Instructors',
                    'type' => 'list',
                    'default' => [],
                    'fields' => [
                        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'default' => ''],
                        ['name' => 'role', 'label' => 'Role', 'type' => 'text', 'default' => ''],
                        ['name' => 'bio', 'label' => 'Bio', 'type' => 'textarea', 'default' => ''],
                        [
                            'name' => 'photosource',
                            'label' => 'Photo source',
                            'type' => 'select',
                            'default' => 'url',
                            'choices' => ['url' => 'Photo URL', 'upload' => 'Uploaded photo'],
                        ],
                        ['name' => 'photourl', 'label' => 'Photo URL', 'type' => 'url', 'default' => '',
                            'showwhen' => ['field' => 'photosource', 'equals' => 'url']],
                        ['name' => 'photofile', 'label' => 'Uploaded photo', 'type' => 'image', 'default' => '',
                            'showwhen' => ['field' => 'photosource', 'equals' => 'upload']],
                    ],
                ],
            ],
            'newsletter' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => ''],
                ['name' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => ''],
                ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'default' => ''],
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'panelbgcolor', 'label' => 'Panel background', 'type' => 'color', 'default' => ''],
                ['name' => 'panelbordercolor', 'label' => 'Panel border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'panelborderwidth', 'label' => 'Panel border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'panelradius', 'label' => 'Panel radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'panelpaddingtop', 'label' => 'Panel padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'panelpaddingright', 'label' => 'Panel padding right (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'panelpaddingbottom', 'label' => 'Panel padding bottom (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'panelpaddingleft', 'label' => 'Panel padding left (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'titlecolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Description colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Description font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'inputbgcolor', 'label' => 'Input background', 'type' => 'color', 'default' => ''],
                ['name' => 'inputbordercolor', 'label' => 'Input border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'inputtextcolor', 'label' => 'Input text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'placeholdercolor', 'label' => 'Placeholder colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttoncolor', 'label' => 'Button background', 'type' => 'color', 'default' => ''],
                ['name' => 'buttontextcolor', 'label' => 'Button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttonradius', 'label' => 'Button radius (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'placeholder', 'label' => 'Email placeholder', 'type' => 'text', 'default' => ''],
                ['name' => 'buttonlabel', 'label' => 'Button label', 'type' => 'text', 'default' => ''],
                ['name' => 'successmessage', 'label' => 'Success message', 'type' => 'text', 'default' => ''],
            ],
            'videohero' => [
                [
                    'name' => 'heading',
                    'label' => 'Heading (use | for a line break)',
                    'type' => 'text',
                    'default' => 'Shop courses and|programmes online',
                ],
                [
                    'name' => 'subtext',
                    'label' => 'Sub text',
                    'type' => 'textarea',
                    'default' => 'Find the right course, purchase securely, and get instant access to expert-led '
                        . 'learning, certificates, and bundled programme offers.',
                ],
                ['name' => 'btn_primary_label', 'label' => 'Primary button label', 'type' => 'text',
                    'default' => 'Browse courses'],
                ['name' => 'btn_primary_url', 'label' => 'Primary button URL', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'btn_primary_label', 'truthy' => true]],
                ['name' => 'btn_secondary_label', 'label' => 'Secondary button label', 'type' => 'text', 'default' => ''],
                ['name' => 'btn_secondary_url', 'label' => 'Secondary button URL', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'btn_secondary_label', 'truthy' => true]],
                ['name' => 'bgcolor', 'label' => 'Background colour', 'type' => 'color', 'default' => ''],
                ['name' => 'accentcolor', 'label' => 'Accent colour', 'type' => 'color', 'default' => ''],
                [
                    'name' => 'video_source',
                    'label' => 'Video source',
                    'type' => 'select',
                    'default' => 'none',
                    'choices' => ['none' => 'No video (image only)', 'upload' => 'Uploaded file', 'url' => 'Video URL'],
                ],
                ['name' => 'video_url', 'label' => 'Video URL (YouTube, Vimeo or .mp4)', 'type' => 'url',
                    'default' => '', 'showwhen' => ['field' => 'video_source', 'equals' => 'url']],
                ['name' => 'video_file', 'label' => 'Video file', 'type' => 'videofile', 'default' => '',
                    'showwhen' => ['field' => 'video_source', 'equals' => 'upload']],
                ['name' => 'video_poster', 'label' => 'Poster image', 'type' => 'image', 'default' => '',
                    'showwhen' => ['field' => 'video_source', 'equals' => ['upload', 'url']]],
                ['name' => 'video_title', 'label' => 'Video caption', 'type' => 'text',
                    'default' => 'Featured course offers',
                    'showwhen' => ['field' => 'video_source', 'equals' => ['upload', 'url']]],
                [
                    'name' => 'infoitems',
                    'label' => 'Info card boxes',
                    'type' => 'list',
                    'default' => [
                        ['icon' => 'mortarboard', 'title' => 'Course bundles',
                            'text' => 'Save on curated learning paths.'],
                        ['icon' => 'lock', 'title' => 'Secure checkout', 'text' => 'Buy with confidence in minutes.'],
                        ['icon' => 'unlock', 'title' => 'Instant enrolment',
                            'text' => 'Start learning right after payment.'],
                    ],
                    'fields' => [
                        ['name' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'default' => 'mortarboard'],
                        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                        ['name' => 'text', 'label' => 'Text', 'type' => 'text', 'default' => ''],
                    ],
                ],
                ['name' => 'showquote', 'label' => 'Show testimonial quote', 'type' => 'checkbox', 'default' => true],
                ['name' => 'quote_text', 'label' => 'Testimonial quote', 'type' => 'textarea',
                    'default' => 'Our mission is to make premium learning easy to discover, purchase, and start.',
                    'showwhen' => ['field' => 'showquote', 'truthy' => true]],
                ['name' => 'quote_author', 'label' => 'Testimonial author', 'type' => 'text',
                    'default' => 'Modern Commerce',
                    'showwhen' => ['field' => 'showquote', 'truthy' => true]],
            ],
            'featuregrid' => [
                // Heading. These two are lifted out of the settings JSON onto the widget
                // row by save_storefront_widget, so declaring them here is what makes the
                // section heading editable in the drawer.
                ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => ''],
                ['name' => 'subtitle', 'label' => 'Subheading', 'type' => 'text', 'default' => ''],

                // Layout.
                [
                    'name' => 'style',
                    'label' => 'Card style',
                    'type' => 'select',
                    'default' => 'topbar',
                    'choices' => [
                        'topbar' => 'Top bar — coloured rule across the card top',
                        'plain' => 'Plain — clean card, no accent',
                        'tile' => 'Icon tile — tinted circle behind the icon',
                        'bordered' => 'Bordered — accent-coloured border',
                    ],
                ],
                [
                    'name' => 'columns',
                    'label' => 'Cards per row (desktop)',
                    'type' => 'select',
                    'default' => '3',
                    'choices' => [
                        '3' => '3 per row',
                        '4' => '4 per row',
                    ],
                ],
                ['name' => 'showheader', 'label' => 'Show section heading', 'type' => 'checkbox', 'default' => true],
                ['name' => 'centercards', 'label' => 'Centre card content', 'type' => 'checkbox', 'default' => true],

                // Cards — up to 12 are rendered; extras beyond that are ignored.
                [
                    'name' => 'cards',
                    'label' => 'Cards (up to 12)',
                    'type' => 'list',
                    'default' => [],
                    'fields' => [
                        ['name' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'default' => 'star'],
                        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                        ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => ''],
                        ['name' => 'iconcolor', 'label' => 'Icon colour', 'type' => 'color', 'default' => ''],
                        ['name' => 'accentcolor', 'label' => 'Accent colour', 'type' => 'color', 'default' => ''],
                        ['name' => 'linklabel', 'label' => 'Link label', 'type' => 'text', 'default' => ''],
                        ['name' => 'linkurl', 'label' => 'Link URL', 'type' => 'url', 'default' => '',
                            'showwhen' => ['field' => 'linklabel', 'truthy' => true]],
                    ],
                ],

                // Styling.
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subheading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subheading font size (px)', 'type' => 'number',
                    'default' => 0],
                ['name' => 'cardbgcolor', 'label' => 'Card background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Card border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Card border weight (px)', 'type' => 'number',
                    'default' => 1],
                ['name' => 'cardradius', 'label' => 'Card radius (px)', 'type' => 'number', 'default' => 12],
                // Widget-level fallback for cards that leave their own colour blank.
                ['name' => 'iconcolor', 'label' => 'Default icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'iconsize', 'label' => 'Icon size (px)', 'type' => 'number', 'default' => 40],
                ['name' => 'iconbgcolor', 'label' => 'Icon tile background', 'type' => 'color', 'default' => '',
                    'showwhen' => ['field' => 'style', 'equals' => 'tile']],
                ['name' => 'cardtitlecolor', 'label' => 'Card title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardtitlefontsize', 'label' => 'Card title font size (px)', 'type' => 'number',
                    'default' => 0],
                ['name' => 'textcolor', 'label' => 'Card text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Card text font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'linkcolor', 'label' => 'Card link colour', 'type' => 'color', 'default' => ''],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 72],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 72],
            ],
            'subscriptionplans' => [
                // Heading. Lifted onto the widget row by save_storefront_widget, so
                // declaring them here is what makes them editable in the drawer.
                ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => ''],
                ['name' => 'subtitle', 'label' => 'Subheading', 'type' => 'text', 'default' => ''],
                ['name' => 'showheader', 'label' => 'Show section heading', 'type' => 'checkbox', 'default' => true],

                // Layout.
                [
                    'name' => 'style',
                    'label' => 'Card style',
                    'type' => 'select',
                    'default' => 'elevated',
                    'choices' => [
                        'elevated' => 'Elevated — raised cards, highlighted plan lifted',
                        'bordered' => 'Bordered — flat cards with an accent border',
                        'minimal' => 'Minimal — plain columns divided by rules',
                    ],
                ],
                [
                    'name' => 'columns',
                    'label' => 'Plans per row (desktop)',
                    'type' => 'select',
                    'default' => '3',
                    'choices' => [
                        '2' => '2 per row',
                        '3' => '3 per row',
                        '4' => '4 per row',
                    ],
                ],

                // Plan selection. Plans themselves are managed on the Subscriptions
                // admin page; this widget only chooses which of them to display.
                [
                    'name' => 'billingcycle',
                    'label' => 'Billing cycle',
                    'type' => 'select',
                    'default' => 'both',
                    'choices' => [
                        'both' => 'Monthly and yearly, with a switch',
                        'monthly' => 'Monthly only',
                        'yearly' => 'Yearly only',
                    ],
                ],
                ['name' => 'defaultcycle', 'label' => 'Cycle selected first', 'type' => 'select',
                    'default' => 'monthly',
                    'choices' => ['monthly' => 'Monthly', 'yearly' => 'Yearly'],
                    'showwhen' => ['field' => 'billingcycle', 'equals' => 'both']],
                ['name' => 'monthlylabel', 'label' => 'Monthly switch label', 'type' => 'text',
                    'default' => 'Monthly',
                    'showwhen' => ['field' => 'billingcycle', 'equals' => 'both']],
                ['name' => 'yearlylabel', 'label' => 'Yearly switch label', 'type' => 'text',
                    'default' => 'Yearly',
                    'showwhen' => ['field' => 'billingcycle', 'equals' => 'both']],
                ['name' => 'savingslabel', 'label' => 'Savings badge next to the yearly switch', 'type' => 'text',
                    'default' => 'Save 2 months',
                    'showwhen' => ['field' => 'billingcycle', 'equals' => 'both']],
                [
                    'name' => 'plancodes',
                    'label' => 'Limit to plan codes (comma separated, blank for all)',
                    'type' => 'text',
                    'default' => '',
                ],
                ['name' => 'maxplans', 'label' => 'Maximum plans to show', 'type' => 'number', 'default' => 4],

                // Card content toggles.
                ['name' => 'showdescription', 'label' => 'Show plan description', 'type' => 'checkbox',
                    'default' => true],
                ['name' => 'showfeatures', 'label' => 'Show feature list', 'type' => 'checkbox', 'default' => true],
                ['name' => 'maxfeatures', 'label' => 'Maximum features per plan', 'type' => 'number', 'default' => 10,
                    'showwhen' => ['field' => 'showfeatures', 'truthy' => true]],
                ['name' => 'showtrial', 'label' => 'Show free-trial note', 'type' => 'checkbox', 'default' => true],
                ['name' => 'showpromo', 'label' => 'Strike through the regular price when a promo is live',
                    'type' => 'checkbox', 'default' => true],
                ['name' => 'highlightfeatured', 'label' => 'Highlight the featured plan', 'type' => 'checkbox',
                    'default' => true],
                ['name' => 'featuredlabel', 'label' => 'Highlight badge label', 'type' => 'text',
                    'default' => 'Most popular',
                    'showwhen' => ['field' => 'highlightfeatured', 'truthy' => true]],

                // Call to action.
                ['name' => 'ctalabel', 'label' => 'Button label', 'type' => 'text', 'default' => 'Choose plan'],
                [
                    'name' => 'ctaurl',
                    'label' => 'Button URL (blank goes straight to checkout for the chosen plan)',
                    'type' => 'url',
                    'default' => '',
                ],

                // Styling.
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subheading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subheading font size (px)', 'type' => 'number',
                    'default' => 0],
                ['name' => 'cardbgcolor', 'label' => 'Card background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Card border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Card border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'cardradius', 'label' => 'Card radius (px)', 'type' => 'number', 'default' => 12],
                ['name' => 'accentcolor', 'label' => 'Accent colour (highlighted plan)', 'type' => 'color',
                    'default' => ''],
                ['name' => 'plannamecolor', 'label' => 'Plan name colour', 'type' => 'color', 'default' => ''],
                ['name' => 'plannamefontsize', 'label' => 'Plan name font size (px)', 'type' => 'number',
                    'default' => 0],
                ['name' => 'pricecolor', 'label' => 'Price colour', 'type' => 'color', 'default' => ''],
                ['name' => 'pricefontsize', 'label' => 'Price font size (px)', 'type' => 'number', 'default' => 40],
                ['name' => 'cyclecolor', 'label' => 'Billing cycle colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textcolor', 'label' => 'Description colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Description font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'featurecolor', 'label' => 'Feature text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'featureiconcolor', 'label' => 'Feature icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttoncolor', 'label' => 'Button background', 'type' => 'color', 'default' => ''],
                ['name' => 'buttontextcolor', 'label' => 'Button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttonradius', 'label' => 'Button radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 72],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 72],
            ],
            'leadermessage' => [
                // Design.
                [
                    'name' => 'style',
                    'label' => 'Design style',
                    'type' => 'select',
                    'default' => 'elevated',
                    'choices' => [
                        'elevated' => 'Elevated editorial — soft-shadowed portrait, accent shape',
                        'overlay' => 'Portrait card — raised card, floating name badge',
                        'quote' => 'Quote-led — pull-quote copy, circular portrait',
                        'flat' => 'Flat — plain heading, portrait and paragraphs',
                    ],
                ],
                [
                    'name' => 'mediaposition',
                    'label' => 'Portrait position',
                    'type' => 'select',
                    'default' => 'right',
                    'choices' => [
                        'right' => 'Portrait right',
                        'left' => 'Portrait left',
                    ],
                ],

                // Content.
                // Each block has a switch so an admin can drop any element they
                // do not need without having to blank its fields.
                ['name' => 'showeyebrow', 'label' => 'Show eyebrow label', 'type' => 'checkbox', 'default' => false],
                ['name' => 'eyebrow', 'label' => 'Eyebrow label', 'type' => 'text', 'default' => '',
                    'showwhen' => ['field' => 'showeyebrow', 'truthy' => true]],
                ['name' => 'showtitle', 'label' => 'Show heading', 'type' => 'checkbox', 'default' => true],
                // Lifted onto the widget row on save (see save_storefront_widget); declaring
                // it here is what makes the heading editable in the drawer.
                ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => '',
                    'showwhen' => ['field' => 'showtitle', 'truthy' => true]],
                [
                    'name' => 'message',
                    'label' => 'Message',
                    'type' => 'textarea',
                    'default' => '',
                ],
                ['name' => 'justifytext', 'label' => 'Justify message text', 'type' => 'checkbox', 'default' => false],

                // Portrait.
                ['name' => 'showphoto', 'label' => 'Show portrait', 'type' => 'checkbox', 'default' => true],
                [
                    'name' => 'photosource',
                    'label' => 'Portrait source',
                    'type' => 'select',
                    'default' => 'upload',
                    'choices' => ['upload' => 'Uploaded photo', 'url' => 'Photo URL'],
                    'showwhen' => ['field' => 'showphoto', 'truthy' => true],
                ],
                ['name' => 'photofile', 'label' => 'Uploaded portrait', 'type' => 'image', 'default' => '',
                    'showwhen' => ['field' => 'photosource', 'equals' => 'upload']],
                ['name' => 'photourl', 'label' => 'Portrait URL', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'photosource', 'equals' => 'url']],

                // Attribution.
                ['name' => 'showattribution', 'label' => 'Show name and role', 'type' => 'checkbox',
                    'default' => true],
                ['name' => 'leadername', 'label' => 'Name', 'type' => 'text', 'default' => '',
                    'showwhen' => ['field' => 'showattribution', 'truthy' => true]],
                ['name' => 'leaderrole', 'label' => 'Role or title', 'type' => 'text', 'default' => '',
                    'showwhen' => ['field' => 'showattribution', 'truthy' => true]],

                // Signature.
                ['name' => 'showsignature', 'label' => 'Show signature image', 'type' => 'checkbox',
                    'default' => false],
                [
                    'name' => 'signaturesource',
                    'label' => 'Signature source',
                    'type' => 'select',
                    'default' => 'upload',
                    'choices' => ['upload' => 'Uploaded signature', 'url' => 'Signature URL'],
                    'showwhen' => ['field' => 'showsignature', 'truthy' => true],
                ],
                ['name' => 'signaturefile', 'label' => 'Uploaded signature', 'type' => 'image', 'default' => '',
                    'showwhen' => ['field' => 'signaturesource', 'equals' => 'upload']],
                ['name' => 'signatureurl', 'label' => 'Signature URL', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'signaturesource', 'equals' => 'url']],

                // Call to action.
                ['name' => 'showcta', 'label' => 'Show button', 'type' => 'checkbox', 'default' => false],
                ['name' => 'ctalabel', 'label' => 'Button label', 'type' => 'text', 'default' => '',
                    'showwhen' => ['field' => 'showcta', 'truthy' => true]],
                ['name' => 'ctaurl', 'label' => 'Button link', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'showcta', 'truthy' => true]],

                // Styling.
                ['name' => 'showaccent', 'label' => 'Show accent rule under heading', 'type' => 'checkbox',
                    'default' => true],
                ['name' => 'bgcolor', 'label' => 'Background colour', 'type' => 'color', 'default' => ''],
                ['name' => 'accentcolor', 'label' => 'Accent colour', 'type' => 'color', 'default' => ''],
                ['name' => 'eyebrowcolor', 'label' => 'Eyebrow colour', 'type' => 'color', 'default' => ''],
                ['name' => 'eyebrowfontsize', 'label' => 'Eyebrow font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'titlecolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Message colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Message font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'namecolor', 'label' => 'Name colour', 'type' => 'color', 'default' => ''],
                ['name' => 'namefontsize', 'label' => 'Name font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'rolecolor', 'label' => 'Role colour', 'type' => 'color', 'default' => ''],
                ['name' => 'rolefontsize', 'label' => 'Role font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'buttoncolor', 'label' => 'Button background', 'type' => 'color', 'default' => '',
                    'showwhen' => ['field' => 'showcta', 'truthy' => true]],
                ['name' => 'buttontextcolor', 'label' => 'Button text colour', 'type' => 'color', 'default' => '',
                    'showwhen' => ['field' => 'showcta', 'truthy' => true]],
                ['name' => 'buttonradius', 'label' => 'Button radius (px)', 'type' => 'number', 'default' => 8,
                    'showwhen' => ['field' => 'showcta', 'truthy' => true]],
                ['name' => 'mediaradius', 'label' => 'Portrait radius (px)', 'type' => 'number', 'default' => 16,
                    'showwhen' => ['field' => 'showphoto', 'truthy' => true]],
                ['name' => 'mediawidth', 'label' => 'Portrait column width (%)', 'type' => 'number', 'default' => 38,
                    'showwhen' => ['field' => 'showphoto', 'truthy' => true]],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 72],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 72],
            ],
            'mediastorycarousel' => [
                [
                    'name' => 'mediaposition',
                    'label' => 'Media position',
                    'type' => 'select',
                    'default' => 'left',
                    'choices' => [
                        'left' => 'Media left',
                        'right' => 'Media right',
                    ],
                ],
                ['name' => 'bgcolor', 'label' => 'Background colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbgcolor', 'label' => 'Story panel background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Story panel border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Story panel border weight (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'cardradius', 'label' => 'Story panel radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'titlecolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Subheading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Subheading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'iconcolor', 'label' => 'Navigation icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'iconbgcolor', 'label' => 'Navigation background', 'type' => 'color', 'default' => ''],
                ['name' => 'mediaradius', 'label' => 'Media radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 20],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 80],
                [
                    'name' => 'navicon',
                    'label' => 'Navigation icon',
                    'type' => 'icon',
                    'default' => 'chevron-right',
                    'choices' => self::navigation_icon_choices(),
                ],
                [
                    'name' => 'slides',
                    'label' => 'Carousel slides',
                    'type' => 'list',
                    'default' => [
                        [
                            'heading' => 'Courses that move careers forward',
                            'subheading' => 'Showcase programmes with clear outcomes, secure checkout, and instant ' .
                                'access so learners can start building valuable skills right away.',
                            'mediatype' => 'image',
                            'mediasource' => 'url',
                            'imageurl' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?' .
                                'auto=format&fit=crop&w=1200&q=80',
                            'imagefile' => '',
                            'videourl' => '',
                            'videofile' => '',
                            'posterurl' => '',
                            'posterimage' => '',
                            'alt' => 'Learners collaborating in a course programme',
                        ],
                        [
                            'heading' => 'From checkout to course access',
                            'subheading' => 'Turn interest into enrolment with trusted product stories, flexible media, ' .
                                'and a focused path from discovery to purchase.',
                            'mediatype' => 'image',
                            'mediasource' => 'url',
                            'imageurl' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?' .
                                'auto=format&fit=crop&w=1200&q=80',
                            'imagefile' => '',
                            'videourl' => '',
                            'videofile' => '',
                            'posterurl' => '',
                            'posterimage' => '',
                            'alt' => 'Course team planning an online learning programme',
                        ],
                    ],
                    'fields' => [
                        ['name' => 'heading', 'label' => 'Heading', 'type' => 'text',
                            'default' => 'Courses that move careers forward'],
                        [
                            'name' => 'subheading',
                            'label' => 'Subheading',
                            'type' => 'textarea',
                            'default' => 'Showcase programmes with clear outcomes, secure checkout, and instant ' .
                                'access so learners can start building valuable skills right away.',
                        ],
                        [
                            'name' => 'mediatype',
                            'label' => 'Media type',
                            'type' => 'select',
                            'default' => 'image',
                            'choices' => ['image' => 'Image', 'video' => 'Video'],
                        ],
                        [
                            'name' => 'mediasource',
                            'label' => 'Media source',
                            'type' => 'select',
                            'default' => 'url',
                            'choices' => ['url' => 'External URL', 'upload' => 'Uploaded file'],
                        ],
                        ['name' => 'imageurl', 'label' => 'Image URL', 'type' => 'url', 'default' => '',
                            'showwhen' => [
                                ['field' => 'mediatype', 'equals' => 'image'],
                                ['field' => 'mediasource', 'equals' => 'url'],
                            ]],
                        ['name' => 'imagefile', 'label' => 'Uploaded image', 'type' => 'image', 'default' => '',
                            'showwhen' => [
                                ['field' => 'mediatype', 'equals' => 'image'],
                                ['field' => 'mediasource', 'equals' => 'upload'],
                            ]],
                        ['name' => 'videourl', 'label' => 'Video URL (YouTube, Vimeo or direct video)', 'type' => 'url',
                            'default' => '',
                            'showwhen' => [
                                ['field' => 'mediatype', 'equals' => 'video'],
                                ['field' => 'mediasource', 'equals' => 'url'],
                            ]],
                        ['name' => 'videofile', 'label' => 'Uploaded video', 'type' => 'videofile', 'default' => '',
                            'showwhen' => [
                                ['field' => 'mediatype', 'equals' => 'video'],
                                ['field' => 'mediasource', 'equals' => 'upload'],
                            ]],
                        ['name' => 'posterurl', 'label' => 'Video poster URL', 'type' => 'url', 'default' => '',
                            'showwhen' => [
                                ['field' => 'mediatype', 'equals' => 'video'],
                                ['field' => 'mediasource', 'equals' => 'url'],
                            ]],
                        ['name' => 'posterimage', 'label' => 'Uploaded video poster', 'type' => 'image', 'default' => '',
                            'showwhen' => [
                                ['field' => 'mediatype', 'equals' => 'video'],
                                ['field' => 'mediasource', 'equals' => 'upload'],
                            ]],
                        ['name' => 'alt', 'label' => 'Media alt text', 'type' => 'text', 'default' => ''],
                    ],
                ],
            ],
            'breadcrumb' => [
                [
                    'name' => 'style',
                    'label' => 'Style',
                    'type' => 'select',
                    'default' => 'imagehero',
                    'choices' => [
                        'imagehero' => 'Image hero',
                        'clean' => 'Clean minimal',
                        'gradient' => 'Gradient media',
                        'pastel' => 'Pastel title band',
                        'illustration' => 'Illustrated learning',
                    ],
                ],
                ['name' => 'title', 'label' => 'Title override', 'type' => 'text', 'default' => ''],
                [
                    'name' => 'subtitle',
                    'label' => 'Subtitle',
                    'type' => 'textarea',
                    'default' => 'Find the right course, buy securely, and start learning with confidence.',
                ],
                ['name' => 'homelabel', 'label' => 'Home label', 'type' => 'text', 'default' => 'Home'],
                ['name' => 'homeurl', 'label' => 'Home URL', 'type' => 'url',
                    'default' => '/local/moderncommerce/index.php'],
                ['name' => 'sectionlabel', 'label' => 'Middle breadcrumb label', 'type' => 'text', 'default' => ''],
                ['name' => 'sectionurl', 'label' => 'Middle breadcrumb URL', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'sectionlabel', 'truthy' => true]],
                [
                    'name' => 'excludedpages',
                    'label' => 'Exclude from pages (comma or new line separated)',
                    'type' => 'textarea',
                    'default' => 'catalog',
                ],
                [
                    'name' => 'backgroundsource',
                    'label' => 'Image source',
                    'type' => 'select',
                    'default' => 'url',
                    'choices' => [
                        'url' => 'Image URL',
                        'upload' => 'Uploaded image',
                    ],
                    'showwhen' => ['field' => 'style', 'equals' => ['imagehero', 'gradient']],
                ],
                [
                    'name' => 'backgroundimage',
                    'label' => 'Background image URL',
                    'type' => 'url',
                    'default' => 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=1800&q=80',
                    'showwhen' => [
                        ['field' => 'style', 'equals' => ['imagehero', 'gradient']],
                        ['field' => 'backgroundsource', 'equals' => 'url'],
                    ],
                ],
                ['name' => 'backgroundfile', 'label' => 'Uploaded background image', 'type' => 'image', 'default' => '',
                    'showwhen' => [
                        ['field' => 'style', 'equals' => ['imagehero', 'gradient']],
                        ['field' => 'backgroundsource', 'equals' => 'upload'],
                    ]],
                ['name' => 'bgcolor', 'label' => 'Background colour', 'type' => 'color', 'default' => ''],
                ['name' => 'overlaycolor', 'label' => 'Overlay colour', 'type' => 'color', 'default' => '',
                    'showwhen' => ['field' => 'style', 'equals' => 'imagehero']],
                ['name' => 'gradientstart', 'label' => 'Gradient start', 'type' => 'color', 'default' => '',
                    'showwhen' => ['field' => 'style', 'equals' => 'gradient']],
                ['name' => 'gradientend', 'label' => 'Gradient end', 'type' => 'color', 'default' => '',
                    'showwhen' => ['field' => 'style', 'equals' => 'gradient']],
                ['name' => 'textcolor', 'label' => 'Text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'accentcolor', 'label' => 'Accent colour', 'type' => 'color', 'default' => ''],
                [
                    'name' => 'alignment',
                    'label' => 'Alignment',
                    'type' => 'select',
                    'default' => 'center',
                    'choices' => [
                        'center' => 'Center',
                        'left' => 'Left',
                        'right' => 'Right',
                    ],
                ],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 82],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 82],
            ],
            'content' => [
                ['name' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text', 'default' => ''],
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => ''],
                ['name' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'default' => 'mortarboard'],
                ['name' => 'bgcolor', 'label' => 'Background colour', 'type' => 'color', 'default' => ''],
                ['name' => 'panelbgcolor', 'label' => 'Content panel background', 'type' => 'color', 'default' => ''],
                ['name' => 'panelbordercolor', 'label' => 'Content panel border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Title font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subtitle colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subtitle font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Body text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Body font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'iconbgcolor', 'label' => 'Icon background', 'type' => 'color', 'default' => ''],
                ['name' => 'iconcolor', 'label' => 'Icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'iconsize', 'label' => 'Icon size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'buttoncolor', 'label' => 'Button background', 'type' => 'color', 'default' => ''],
                ['name' => 'buttontextcolor', 'label' => 'Button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttonradius', 'label' => 'Button radius (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'mediaradius', 'label' => 'Media radius (px)', 'type' => 'number', 'default' => 8],
                [
                    'name' => 'layout',
                    'label' => 'Layout',
                    'type' => 'select',
                    'default' => 'card',
                    'choices' => [
                        'card' => 'Card',
                        'centered' => 'Centered',
                        'split' => 'Split',
                    ],
                ],
                [
                    'name' => 'mediaposition',
                    'label' => 'Media position',
                    'type' => 'select',
                    'default' => 'right',
                    'choices' => [
                        'left' => 'Media left',
                        'right' => 'Media right',
                    ],
                    'showwhen' => ['field' => 'layout', 'equals' => ['card', 'split']],
                ],
                [
                    'name' => 'cardradius',
                    'label' => 'Card border radius (px)',
                    'type' => 'number',
                    'default' => 8,
                    'showwhen' => ['field' => 'layout', 'equals' => 'card'],
                ],
                [
                    'name' => 'body',
                    'label' => 'Body',
                    'type' => 'textarea',
                    'default' => 'Describe the offer, buyer benefit, or page message.',
                ],
                [
                    'name' => 'benefits',
                    'label' => 'Benefit rows',
                    'type' => 'list',
                    'default' => [
                        [
                            'number' => '01',
                            'title' => 'Interactive Learning',
                            'text' => 'Engage with experienced instructors in real-time.',
                        ],
                        [
                            'number' => '02',
                            'title' => 'Personalized Approach',
                            'text' => 'Tailored to your goals, pace, and proficiency level.',
                        ],
                        [
                            'number' => '03',
                            'title' => 'Flexibility and Accessibility',
                            'text' => 'Accessible from any device, no matter where you are.',
                        ],
                    ],
                    'fields' => [
                        ['name' => 'number', 'label' => 'Number', 'type' => 'text', 'default' => ''],
                        ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => ''],
                        ['name' => 'text', 'label' => 'Description', 'type' => 'textarea', 'default' => ''],
                    ],
                ],
                ['name' => 'benefitnumbercolor', 'label' => 'Benefit number colour', 'type' => 'color', 'default' => ''],
                ['name' => 'benefitnumberfontsize', 'label' => 'Benefit number font size (px)', 'type' => 'number',
                    'default' => 0],
                ['name' => 'benefittitlecolor', 'label' => 'Benefit heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'benefittitlefontsize', 'label' => 'Benefit heading font size (px)', 'type' => 'number',
                    'default' => 0],
                ['name' => 'benefittextcolor', 'label' => 'Benefit description colour', 'type' => 'color', 'default' => ''],
                ['name' => 'benefittextfontsize', 'label' => 'Benefit description font size (px)', 'type' => 'number',
                    'default' => 0],
                ['name' => 'benefitbordercolor', 'label' => 'Benefit divider colour', 'type' => 'color', 'default' => ''],
                [
                    'name' => 'imagesource',
                    'label' => 'Image source',
                    'type' => 'select',
                    'default' => 'upload',
                    'choices' => ['upload' => 'Uploaded image', 'url' => 'Image URL'],
                ],
                ['name' => 'imagefile', 'label' => 'Uploaded image', 'type' => 'image', 'default' => '',
                    'showwhen' => ['field' => 'imagesource', 'equals' => 'upload']],
                ['name' => 'imageurl', 'label' => 'Image URL', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'imagesource', 'equals' => 'url']],
                ['name' => 'ctalabel', 'label' => 'Button label', 'type' => 'text', 'default' => ''],
                ['name' => 'ctaurl', 'label' => 'Button URL', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'ctalabel', 'truthy' => true]],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 72],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 72],
                ['name' => 'paddingleft', 'label' => 'Padding left (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingright', 'label' => 'Padding right (px)', 'type' => 'number', 'default' => 0],
            ],
            'learningpromise' => [
                [
                    'name' => 'title',
                    'label' => 'Heading',
                    'type' => 'text',
                    'default' => 'Skills are the key to unlocking potential',
                ],
                [
                    'name' => 'body',
                    'label' => 'Text',
                    'type' => 'textarea',
                    'default' => 'Whether you want to learn a new skill, train your team, or invest in a full ' .
                        'programme, you are in the right place. Our course marketplace helps you find the right ' .
                        'offer, buy with confidence, and start learning right away.',
                ],
                ['name' => 'bgcolor', 'label' => 'Background colour', 'type' => 'color', 'default' => ''],
                ['name' => 'headingcolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'headingfontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Text font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
            ],
            'belief' => [
                ['name' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => 'We believe'],
                [
                    'name' => 'subtitle',
                    'label' => 'Subheading',
                    'type' => 'text',
                    'default' => 'Learning is the source of human progress.',
                ],
                ['name' => 'bgcolor', 'label' => 'Background colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subheading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subheading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'iconcolor', 'label' => 'Icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'iconsize', 'label' => 'Icon size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Point text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Point text font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'labelcolor', 'label' => 'Closing text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'labelfontsize', 'label' => 'Closing font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                [
                    'name' => 'items',
                    'label' => 'Belief points',
                    'type' => 'list',
                    'default' => [
                        [
                            'icon' => 'globe2',
                            'text' => 'It helps learners move from uncertainty to capability, from interest to ' .
                                'purchase, and from enrolment to real progress.',
                        ],
                        [
                            'icon' => 'people',
                            'text' => 'It can transform careers, teams, families, and communities when the right ' .
                                'course is easy to find and start.',
                        ],
                        [
                            'icon' => 'graph-up-arrow',
                            'text' => 'No matter where someone begins, accessible courses and programmes can unlock ' .
                                'new skills, confidence, and opportunity.',
                        ],
                        [
                            'icon' => 'bank',
                            'text' => 'That is why this store brings trusted instructors, practical programmes, ' .
                                'secure checkout, and instant enrolment together.',
                        ],
                    ],
                    'fields' => [
                        ['name' => 'icon', 'label' => 'Bootstrap icon', 'type' => 'icon', 'default' => 'globe2'],
                        ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => ''],
                    ],
                ],
                [
                    'name' => 'closing',
                    'label' => 'Closing statement',
                    'type' => 'textarea',
                    'default' => 'So anyone, anywhere can buy the right course and turn learning into opportunity.',
                ],
            ],
            'policy' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => ''],
                ['name' => 'effectivedate', 'label' => 'Effective date', 'type' => 'text', 'default' => ''],
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbgcolor', 'label' => 'Content background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Content border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Content border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'cardradius', 'label' => 'Content radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'titlecolor', 'label' => 'Title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Title font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subtitle colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subtitle font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'labelcolor', 'label' => 'Section heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'labelfontsize', 'label' => 'Section heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Body text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Body font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                [
                    'name' => 'sections',
                    'label' => 'Policy sections',
                    'type' => 'list',
                    'default' => [],
                    'fields' => [
                        ['name' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => ''],
                        ['name' => 'body', 'label' => 'Body', 'type' => 'textarea', 'default' => ''],
                        [
                            'name' => 'bullets',
                            'label' => 'Bullet points (one per line)',
                            'type' => 'textarea',
                            'default' => '',
                        ],
                    ],
                ],
            ],
            'faq' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Common questions'],
                ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => ''],
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Title font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subtitle colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subtitle font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'itembgcolor', 'label' => 'Question background', 'type' => 'color', 'default' => ''],
                ['name' => 'itembordercolor', 'label' => 'Question border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Question border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'cardradius', 'label' => 'Question radius (px)', 'type' => 'number', 'default' => 6],
                ['name' => 'questioncolor', 'label' => 'Question text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'labelfontsize', 'label' => 'Question font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'answercolor', 'label' => 'Answer text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Answer font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'iconcolor', 'label' => 'Accordion icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                [
                    'name' => 'items',
                    'label' => 'Questions',
                    'type' => 'list',
                    'default' => [],
                    'fields' => [
                        ['name' => 'question', 'label' => 'Question', 'type' => 'text', 'default' => ''],
                        ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'default' => ''],
                    ],
                ],
            ],
            'cta' => [
                ['name' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => 'Ready to start learning?'],
                [
                    'name' => 'text',
                    'label' => 'Text',
                    'type' => 'textarea',
                    'default' => 'Browse courses, bundles, and programmes built for practical progress.',
                ],
                ['name' => 'bgcolor', 'label' => 'Band background', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Text font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'primarybuttoncolor', 'label' => 'Primary button background', 'type' => 'color', 'default' => ''],
                ['name' => 'primarybuttontextcolor', 'label' => 'Primary button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'secondarybuttoncolor', 'label' => 'Secondary button background', 'type' => 'color', 'default' => ''],
                [
                    'name' => 'secondarybuttontextcolor',
                    'label' => 'Secondary button text colour',
                    'type' => 'color',
                    'default' => '',
                ],
                ['name' => 'buttonradius', 'label' => 'Button radius (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'cardradius', 'label' => 'Band radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'primarylabel', 'label' => 'Primary button label', 'type' => 'text', 'default' => 'Browse courses'],
                ['name' => 'primaryurl', 'label' => 'Primary button URL', 'type' => 'url',
                    'default' => '/local/moderncommerce/index.php',
                    'showwhen' => ['field' => 'primarylabel', 'truthy' => true]],
                ['name' => 'secondarylabel', 'label' => 'Secondary button label', 'type' => 'text', 'default' => ''],
                ['name' => 'secondaryurl', 'label' => 'Secondary button URL', 'type' => 'url', 'default' => '',
                    'showwhen' => ['field' => 'secondarylabel', 'truthy' => true]],
                [
                    'name' => 'tone',
                    'label' => 'Tone',
                    'type' => 'select',
                    'default' => 'primary',
                    'choices' => [
                        'primary' => 'Primary',
                        'quiet' => 'Quiet',
                        'success' => 'Success',
                    ],
                ],
            ],
            'supportform' => [
                ['name' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => 'How can we help?'],
                [
                    'name' => 'description',
                    'label' => 'Description',
                    'type' => 'textarea',
                    'default' => 'Send us your order, access, payment, refund, or subscription question.',
                ],
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbgcolor', 'label' => 'Form background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Form border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Form border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'cardradius', 'label' => 'Form radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'titlecolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Description colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Description font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'formlabelcolor', 'label' => 'Field label colour', 'type' => 'color', 'default' => ''],
                ['name' => 'inputbgcolor', 'label' => 'Input background', 'type' => 'color', 'default' => ''],
                ['name' => 'inputbordercolor', 'label' => 'Input border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'inputtextcolor', 'label' => 'Input text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttoncolor', 'label' => 'Submit button background', 'type' => 'color', 'default' => ''],
                ['name' => 'buttontextcolor', 'label' => 'Submit button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'secondarybuttoncolor', 'label' => 'Email button background', 'type' => 'color', 'default' => ''],
                ['name' => 'secondarybuttontextcolor', 'label' => 'Email button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttonradius', 'label' => 'Button radius (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'buttonlabel', 'label' => 'Submit button label', 'type' => 'text',
                    'default' => 'Send support request'],
                ['name' => 'emailbuttonlabel', 'label' => 'Email button label', 'type' => 'text',
                    'default' => 'Email support'],
                ['name' => 'messagelabel', 'label' => 'Message label', 'type' => 'text', 'default' => 'Message'],
                [
                    'name' => 'messageplaceholder',
                    'label' => 'Message placeholder',
                    'type' => 'textarea',
                    'default' => 'Tell us what happened and which course, programme, bundle, or order is affected.',
                ],
            ],
            'contactcards' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Need quick help?'],
                ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => 'Use the right path for your issue.'],
                ['name' => 'bgcolor', 'label' => 'Section background', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Title font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'subtitlecolor', 'label' => 'Subtitle colour', 'type' => 'color', 'default' => ''],
                ['name' => 'subtitlefontsize', 'label' => 'Subtitle font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'cardbgcolor', 'label' => 'Card background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Card border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Card border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'cardradius', 'label' => 'Card radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'iconbgcolor', 'label' => 'Icon background', 'type' => 'color', 'default' => ''],
                ['name' => 'iconcolor', 'label' => 'Icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'iconsize', 'label' => 'Icon size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'labelcolor', 'label' => 'Card heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'labelfontsize', 'label' => 'Card heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Card text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Card text font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'linkcolor', 'label' => 'Link colour', 'type' => 'color', 'default' => ''],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                [
                    'name' => 'cards',
                    'label' => 'Cards',
                    'type' => 'list',
                    'default' => [],
                    'fields' => [
                        ['name' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'default' => 'life-preserver'],
                        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                        ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'default' => ''],
                        ['name' => 'linklabel', 'label' => 'Link label', 'type' => 'text', 'default' => ''],
                        ['name' => 'linkurl', 'label' => 'Link URL', 'type' => 'url', 'default' => '',
                            'showwhen' => ['field' => 'linklabel', 'truthy' => true]],
                    ],
                ],
            ],
            'footer' => [
                [
                    'name' => 'style',
                    'label' => 'Footer style',
                    'type' => 'select',
                    'default' => 'default',
                    'choices' => [
                        'default' => 'Default',
                        'modern-classical' => 'Modern Classical',
                        'enterprise-navy' => 'Enterprise Navy',
                    ],
                ],
                [
                    'name' => 'mode',
                    'label' => 'Colour mode',
                    'type' => 'select',
                    'default' => 'light',
                    'choices' => ['light' => 'Light', 'dark' => 'Dark'],
                    'showwhen' => ['field' => 'style', 'equals' => 'default'],
                ],
                [
                    'name' => 'logosource',
                    'label' => 'Logo source',
                    'type' => 'select',
                    'default' => 'theme',
                    'choices' => logo_source::choices(),
                ],
                ['name' => 'logo', 'label' => 'Custom logo (used when source is "Custom upload")',
                    'type' => 'image', 'default' => '',
                    'showwhen' => ['field' => 'logosource', 'equals' => ['', 'custom']]],
                ['name' => 'logoheight', 'label' => 'Logo height (px)', 'type' => 'number', 'default' => 42],
                ['name' => 'bgcolor', 'label' => 'Footer background', 'type' => 'color', 'default' => ''],
                ['name' => 'panelbgcolor', 'label' => 'Panel background', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Column heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Column heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Text font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'linkcolor', 'label' => 'Link colour', 'type' => 'color', 'default' => ''],
                ['name' => 'iconbgcolor', 'label' => 'Icon background', 'type' => 'color', 'default' => ''],
                ['name' => 'iconcolor', 'label' => 'Icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'inputbgcolor', 'label' => 'Subscribe input background', 'type' => 'color', 'default' => ''],
                ['name' => 'inputbordercolor', 'label' => 'Subscribe input border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'inputtextcolor', 'label' => 'Subscribe input text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttoncolor', 'label' => 'Subscribe button background', 'type' => 'color', 'default' => ''],
                ['name' => 'buttontextcolor', 'label' => 'Subscribe button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'paddingtop', 'label' => 'Padding top (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'brandname', 'label' => 'Brand name (shown if no logo)', 'type' => 'text', 'default' => ''],
                [
                    'name' => 'description',
                    'label' => 'Brand description',
                    'type' => 'textarea',
                    'default' => 'Discover practical courses, programmes, and bundles with secure checkout and learner support.',
                    'showwhen' => ['field' => 'style', 'equals' => ['modern-classical', 'enterprise-navy']],
                ],
                [
                    'name' => 'address',
                    'label' => 'Address (one line per row)',
                    'type' => 'textarea',
                    'default' => "4967 Sardis Sta, Victoria 8007, Montreal\nUnited State",
                    'showwhen' => ['field' => 'style', 'equals' => ['default', 'modern-classical']],
                ],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'default' => '+1246-345-0695',
                    'showwhen' => ['field' => 'style', 'equals' => ['default', 'modern-classical']]],
                ['name' => 'email', 'label' => 'Contact email', 'type' => 'text', 'default' => 'support@learnup.com',
                    'showwhen' => ['field' => 'style', 'equals' => ['default', 'modern-classical']]],
                ['name' => 'languagelabel', 'label' => 'Language label', 'type' => 'text', 'default' => 'English',
                    'showwhen' => ['field' => 'style', 'equals' => 'enterprise-navy']],
                [
                    'name' => 'subscribeplaceholder',
                    'label' => 'Subscribe input placeholder',
                    'type' => 'text',
                    'default' => 'Enter your email to subscribe',
                    'showwhen' => ['field' => 'style', 'equals' => 'enterprise-navy'],
                ],
                ['name' => 'compliancelabel', 'label' => 'Compliance label', 'type' => 'text',
                    'default' => 'WCAG 2.1 AA',
                    'showwhen' => ['field' => 'style', 'equals' => 'enterprise-navy']],
                [
                    'name' => 'columns',
                    'label' => 'Link columns',
                    'type' => 'list',
                    'default' => [
                        [
                            'title' => 'Navigations',
                            'links' => "About Us | #\nFAQs Page | #\nCheckout | #\nContact | #\nBlog | #",
                        ],
                        [
                            'title' => 'New Categories',
                            'links' => "Designing | #\nBusiness | #\nSoftware | #\nWordPress | #\nPHP | #",
                        ],
                        [
                            'title' => 'Help & Support',
                            'links' => "Documentation | #\nLive Chat | #\nMail Us | #\nPrivacy | #\nFAQs | #",
                        ],
                    ],
                    'fields' => [
                        ['name' => 'title', 'label' => 'Column title', 'type' => 'text', 'default' => ''],
                        [
                            'name' => 'links',
                            'label' => 'Links (one "Label | URL" per line)',
                            'type' => 'textarea',
                            'default' => '',
                        ],
                    ],
                ],
                ['name' => 'appstitle', 'label' => 'Apps column title', 'type' => 'text',
                    'default' => 'Download Apps',
                    'showwhen' => ['field' => 'style', 'equals' => 'default']],
                ['name' => 'googleplayurl', 'label' => 'Google Play URL', 'type' => 'url',
                    'default' => 'https://play.google.com/store',
                    'showwhen' => ['field' => 'style', 'equals' => 'default']],
                ['name' => 'appstoreurl', 'label' => 'App Store URL', 'type' => 'url',
                    'default' => 'https://www.apple.com/app-store/',
                    'showwhen' => ['field' => 'style', 'equals' => 'default']],
                [
                    'name' => 'social',
                    'label' => 'Social links',
                    'type' => 'list',
                    'default' => [
                        ['icon' => 'facebook', 'url' => '#', 'label' => 'Facebook'],
                        ['icon' => 'twitter-x', 'url' => '#', 'label' => 'Twitter'],
                        ['icon' => 'instagram', 'url' => '#', 'label' => 'Instagram'],
                        ['icon' => 'linkedin', 'url' => '#', 'label' => 'LinkedIn'],
                    ],
                    'fields' => [
                        ['name' => 'icon', 'label' => 'Icon', 'type' => 'icon', 'default' => 'facebook'],
                        ['name' => 'url', 'label' => 'URL', 'type' => 'url', 'default' => ''],
                        ['name' => 'label', 'label' => 'Label', 'type' => 'text', 'default' => ''],
                    ],
                ],
                [
                    'name' => 'copyright',
                    'label' => 'Copyright text ({year} and {sitename} are replaced)',
                    'type' => 'text',
                    'default' => '© {year} {sitename}',
                ],
            ],
            'catalog' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
                ['name' => 'showheader', 'label' => 'Show hero section', 'type' => 'checkbox', 'default' => true],
                ['name' => 'perpage', 'label' => 'Items per page', 'type' => 'number', 'default' => 12],
                [
                    'name' => 'sidebarposition',
                    'label' => 'Sidebar position',
                    'type' => 'select',
                    'default' => 'left',
                    'choices' => ['left' => 'Left', 'right' => 'Right'],
                ],
                ['name' => 'bgcolor', 'label' => 'Background colour', 'type' => 'color', 'default' => ''],
                ['name' => 'herobgcolor', 'label' => 'Hero background', 'type' => 'color', 'default' => ''],
                ['name' => 'herobordercolor', 'label' => 'Hero border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'heroradius', 'label' => 'Hero radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'eyebrowcolor', 'label' => 'Eyebrow colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlecolor', 'label' => 'Heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'titlefontsize', 'label' => 'Heading font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'textcolor', 'label' => 'Body text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'textfontsize', 'label' => 'Body font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'accentcolor', 'label' => 'Accent colour', 'type' => 'color', 'default' => ''],
                ['name' => 'heropanelbgcolor', 'label' => 'Hero panel background', 'type' => 'color', 'default' => ''],
                ['name' => 'heropanelbordercolor', 'label' => 'Hero panel border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'heropaneltextcolor', 'label' => 'Hero panel text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'heropanelaccentcolor', 'label' => 'Hero panel icon colour', 'type' => 'color', 'default' => ''],
                ['name' => 'heropanelvaluecolor', 'label' => 'Hero panel number colour', 'type' => 'color', 'default' => ''],
                [
                    'name' => 'heropanelvaluefontsize',
                    'label' => 'Hero panel number font size (px)',
                    'type' => 'number',
                    'default' => 0,
                ],
                ['name' => 'cardbgcolor', 'label' => 'Course card background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardbordercolor', 'label' => 'Course card border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardborderwidth', 'label' => 'Course card border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'cardradius', 'label' => 'Course card radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'cardfooterbgcolor', 'label' => 'Course card footer background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardtitlecolor', 'label' => 'Course title colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardtitlefontsize', 'label' => 'Course title font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'cardtextcolor', 'label' => 'Course meta text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'cardmetabgcolor', 'label' => 'Category chip background', 'type' => 'color', 'default' => ''],
                ['name' => 'cardmetatextcolor', 'label' => 'Category chip text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'ratingcolor', 'label' => 'Rating star colour', 'type' => 'color', 'default' => ''],
                ['name' => 'ratingtextcolor', 'label' => 'Rating number colour', 'type' => 'color', 'default' => ''],
                ['name' => 'originalpricecolor', 'label' => 'Original price colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttoncolor', 'label' => 'Button background', 'type' => 'color', 'default' => ''],
                ['name' => 'buttontextcolor', 'label' => 'Button text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'buttonradius', 'label' => 'Button radius (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'badgebgcolor', 'label' => 'Badge background', 'type' => 'color', 'default' => ''],
                ['name' => 'badgebordercolor', 'label' => 'Badge border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'badgetextcolor', 'label' => 'Badge text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'badgeradius', 'label' => 'Badge radius (px)', 'type' => 'number', 'default' => 6],
                ['name' => 'badgefontsize', 'label' => 'Badge font size (px)', 'type' => 'number', 'default' => 0],
                ['name' => 'coursebadgebgcolor', 'label' => 'Course badge background', 'type' => 'color', 'default' => ''],
                ['name' => 'coursebadgebordercolor', 'label' => 'Course badge border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'coursebadgetextcolor', 'label' => 'Course badge text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'programbadgebgcolor', 'label' => 'Programme badge background', 'type' => 'color', 'default' => ''],
                [
                    'name' => 'programbadgebordercolor',
                    'label' => 'Programme badge border colour',
                    'type' => 'color',
                    'default' => '',
                ],
                ['name' => 'programbadgetextcolor', 'label' => 'Programme badge text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'bundlebadgebgcolor', 'label' => 'Bundle badge background', 'type' => 'color', 'default' => ''],
                ['name' => 'bundlebadgebordercolor', 'label' => 'Bundle badge border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'bundlebadgetextcolor', 'label' => 'Bundle badge text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'filterbgcolor', 'label' => 'Filter panel background', 'type' => 'color', 'default' => ''],
                ['name' => 'filterbordercolor', 'label' => 'Filter panel border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'filterborderwidth', 'label' => 'Filter border weight (px)', 'type' => 'number', 'default' => 1],
                ['name' => 'filterradius', 'label' => 'Filter radius (px)', 'type' => 'number', 'default' => 8],
                ['name' => 'filtertitlecolor', 'label' => 'Filter heading colour', 'type' => 'color', 'default' => ''],
                ['name' => 'filtertextcolor', 'label' => 'Filter text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'inputbgcolor', 'label' => 'Input background', 'type' => 'color', 'default' => ''],
                ['name' => 'inputbordercolor', 'label' => 'Input border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'inputtextcolor', 'label' => 'Input text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'placeholdercolor', 'label' => 'Input placeholder colour', 'type' => 'color', 'default' => ''],
                ['name' => 'tabbgcolor', 'label' => 'Tab background', 'type' => 'color', 'default' => ''],
                ['name' => 'tabbordercolor', 'label' => 'Tab border colour', 'type' => 'color', 'default' => ''],
                ['name' => 'tabtextcolor', 'label' => 'Tab text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'tabactivebgcolor', 'label' => 'Active tab background', 'type' => 'color', 'default' => ''],
                ['name' => 'tabactivetextcolor', 'label' => 'Active tab text colour', 'type' => 'color', 'default' => ''],
                ['name' => 'pricecolor', 'label' => 'Price colour', 'type' => 'color', 'default' => ''],
                ['name' => 'paddingtop', 'label' => 'Padding top', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingbottom', 'label' => 'Padding bottom', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingleft', 'label' => 'Padding left', 'type' => 'number', 'default' => 0],
                ['name' => 'paddingright', 'label' => 'Padding right', 'type' => 'number', 'default' => 0],
                ['name' => 'margintop', 'label' => 'Margin top', 'type' => 'number', 'default' => 0],
                ['name' => 'marginbottom', 'label' => 'Margin bottom', 'type' => 'number', 'default' => 0],
            ],
        ];

        return self::localize_fields($schemas[$type] ?? []);
    }

    /**
     * Curated Bootstrap icon choices for carousel navigation buttons.
     *
     * @return array<string, string>
     */
    private static function navigation_icon_choices(): array {
        return [
            'chevron-right' => 'Chevron',
            'arrow-right' => 'Arrow',
            'caret-right' => 'Caret',
            'caret-right-fill' => 'Filled caret',
            'chevron-compact-right' => 'Compact chevron',
            'chevron-double-right' => 'Double chevron',
            'arrow-right-circle' => 'Arrow circle',
            'arrow-right-short' => 'Short arrow',
        ];
    }

    /**
     * Shared field set for the featured and related product-list widgets.
     *
     * @return array
     */
    private static function product_list_fields(): array {
        return [
            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
            ['name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => ''],
            [
                'name' => 'align',
                'label' => 'Heading alignment',
                'type' => 'select',
                'default' => 'left',
                'choices' => ['left' => 'Left', 'center' => 'Center'],
            ],
            [
                'name' => 'navposition',
                'label' => 'Arrow controls position',
                'type' => 'select',
                'default' => 'topright',
                'choices' => [
                    'topleft' => 'Top left',
                    'topcenter' => 'Top center',
                    'topright' => 'Top right',
                    'bottomleft' => 'Bottom left',
                    'bottomcenter' => 'Bottom center',
                    'bottomright' => 'Bottom right',
                ],
                'showwhen' => ['field' => 'layout', 'equals' => 'carousel'],
            ],
            [
                'name' => 'coursetype',
                'label' => 'Product type',
                'type' => 'select',
                'default' => '',
                'choices' => [
                    '' => 'All types',
                    'Course' => 'Course',
                    'Bundle' => 'Bundle',
                    'Program' => 'Program',
                ],
            ],
            [
                'name' => 'categoryid',
                'label' => 'Category',
                'type' => 'select',
                'default' => 0,
                'choices' => self::category_choices(),
            ],
            [
                'name' => 'sort',
                'label' => 'Sort by',
                'type' => 'select',
                'default' => 'popular',
                'choices' => [
                    'popular' => 'Most popular',
                    'newest' => 'Newest',
                    'pricelow' => 'Price: low to high',
                    'pricehigh' => 'Price: high to low',
                ],
            ],
            ['name' => 'perpage', 'label' => 'Count', 'type' => 'number', 'default' => 8],
            [
                'name' => 'layout',
                'label' => 'Layout',
                'type' => 'select',
                'default' => 'carousel',
                'choices' => ['carousel' => 'Carousel', 'grid' => 'Grid'],
            ],
            [
                'name' => 'columns',
                'label' => 'Columns',
                'type' => 'select',
                'default' => 4,
                'choices' => ['2' => '2', '3' => '3', '4' => '4', '5' => '5'],
            ],
            ['name' => 'buttoncolor', 'label' => 'Button background', 'type' => 'color', 'default' => ''],
            ['name' => 'buttontextcolor', 'label' => 'Button text colour', 'type' => 'color', 'default' => ''],
            ['name' => 'cardbgcolor', 'label' => 'Card background', 'type' => 'color', 'default' => ''],
            ['name' => 'cardbordercolor', 'label' => 'Card border colour', 'type' => 'color', 'default' => ''],
            ['name' => 'cardborderwidth', 'label' => 'Card border weight (px)', 'type' => 'number', 'default' => 0],
        ];
    }

    /**
     * Localise schema labels and choice labels recursively.
     *
     * @param array $fields Field definitions.
     * @return array Localised field definitions.
     */
    private static function localize_fields(array $fields): array {
        foreach ($fields as &$field) {
            if (isset($field['label']) && is_string($field['label'])) {
                $field['label'] = self::localize_schema_text($field['label']);
            }
            if (isset($field['choices']) && is_array($field['choices'])) {
                foreach ($field['choices'] as $value => $label) {
                    if (is_string($label) && $label !== '') {
                        $field['choices'][$value] = self::localize_schema_text($label);
                    }
                }
            }
            if (isset($field['fields']) && is_array($field['fields'])) {
                $field['fields'] = self::localize_fields($field['fields']);
            }
        }
        unset($field);

        return $fields;
    }

    /**
     * Localise a schema text label using a deterministic key.
     *
     * @param string $text English source text.
     * @return string Localised text.
     */
    private static function localize_schema_text(string $text): string {
        $identifier = 'widgetfield_' . trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($text)), '_');
        return get_string_manager()->string_exists($identifier, 'local_moderncommerce')
            ? get_string($identifier, 'local_moderncommerce')
            : $text;
    }

    /**
     * Build the category dropdown choices from the visible Modern Commerce product categories,
     * in the display order set on the admin categories screen.
     *
     * @return array<string, string>
     */
    private static function category_choices(): array {
        $choices = ['' => get_string('choosedots')];
        try {
            foreach (\local_moderncommerce\services\category_service::get_visible_categories() as $category) {
                $choices[(string)$category['id']] = $category['name'];
            }
        } catch (\Throwable $e) {
            return $choices;
        }
        return $choices;
    }
}
