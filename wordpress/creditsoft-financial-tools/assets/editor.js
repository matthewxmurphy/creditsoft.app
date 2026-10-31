/* Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Built Not Begged: https://builtnotbegged.com. Recorded policy identity: Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later. CreditSoft adapter 2026.10.1 UNRELEASED.
 */
(function (wp) {
    const el = wp.element.createElement;
    const options = [
        ['budget', 'Budget coaching'], ['payoff', 'Payoff planner'], ['utilization', 'Utilization targets'],
        ['prep', 'Consultation prep'], ['strategy', 'Strategy planner'], ['simulator', 'Score scenario planner'],
        ['optimizer', 'Utilization optimizer'], ['readiness', 'Funding readiness'], ['negative', 'Negative-item review'],
        ['comparison', 'Debt comparison'], ['timeline', 'Action timeline'], ['recommendation', 'Account helper']
    ].map(function (pair) { return { value: pair[0], label: pair[1] }; });
    wp.blocks.registerBlockType('creditsoft/financial-tool', {
        title: 'CreditSoft financial tool', category: 'widgets', icon: 'chart-area',
        attributes: { slug: { type: 'string', default: 'budget' } }, supports: { html: false, multiple: false },
        edit: function (props) {
            return el('div', wp.blockEditor.useBlockProps(),
                el(wp.components.SelectControl, { label: 'Financial tool', value: props.attributes.slug,
                    options: options, onChange: function (slug) { props.setAttributes({ slug: slug }); } }),
                el('p', {}, 'The selected calculator runs locally on the published page. Use one calculator per page.'));
        },
        save: function () { return null; }
    });
}(window.wp));
