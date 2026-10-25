<?php

/**
 * Agency / Client license content.
 *
 * Suggested service packages, a simple contract template and pricing tips.
 */
return [

    'packages' => [
        [
            'key' => 'starter',
            'name' => 'Starter',
            'monthly_fee' => 300,
            'issues_per_month' => 2,
            'includes' => [
                'Two newsletters written and published each month',
                'Newsletter setup with name, tagline and sign-up page',
                'One round of edits on each newsletter before it goes out',
                'A simple monthly summary of reader numbers',
            ],
            'best_for' => 'Small local businesses and solo professionals starting their first newsletter.',
        ],
        [
            'key' => 'growth',
            'name' => 'Growth',
            'monthly_fee' => 600,
            'issues_per_month' => 4,
            'includes' => [
                'Four newsletters written and published each month',
                'A monthly content plan agreed with the client in advance',
                'Two rounds of edits on each newsletter',
                'Subject line testing ideas and suggestions',
                'Monthly report with reader growth and most popular newsletters',
            ],
            'best_for' => 'Growing businesses that want a steady weekly presence in their customers\' inboxes.',
        ],
        [
            'key' => 'premium',
            'name' => 'Premium',
            'monthly_fee' => 1200,
            'issues_per_month' => 8,
            'includes' => [
                'Up to eight newsletters each month',
                'Quarterly strategy call to plan topics and goals',
                'Unlimited reasonable edits within the agreed schedule',
                'Sponsor or promotion slots planned into newsletters where relevant',
                'Ideas to grow the reader list, such as sign-up page improvements',
                'Detailed monthly report with recommendations',
            ],
            'best_for' => 'Established businesses and brands that treat their newsletter as a key marketing channel.',
        ],
    ],

    'contract' => implode("\n\n", [
        'Template only. Have a lawyer review before use.',
        'NEWSLETTER MANAGEMENT SERVICES AGREEMENT',
        'This agreement is made between {agency_name} ("the Agency") and {client_name} ("the Client"), contactable at {client_email}. It starts on {start_date}.',
        '1. Services. The Agency will plan, write, edit and publish email newsletters on behalf of the Client under the {package_name} package. This includes up to {issues_per_month} newsletters per month, along with any other items listed in the package description agreed by both parties. Any work outside this scope will be agreed in writing and may be charged separately.',
        '2. Fees and payment. The Client will pay the Agency {monthly_fee} per month for the services. Fees are payable in advance at the start of each monthly period. Invoices are due within 14 days of the invoice date unless otherwise agreed. The Agency may pause services if payment is overdue, after giving the Client written notice.',
        '3. Client responsibilities. The Client agrees to review and approve each newsletter within a reasonable time before it is published, and the Agency will not publish a newsletter without approval unless the Client has given written permission to do so. The Client will provide accurate information about their business, products, offers and any legal requirements that apply to their industry. The Client is responsible for the accuracy of facts, prices and claims they supply or approve.',
        '4. Subscriber list and consent. The Client owns their subscriber list at all times. The Client confirms that every person on the list has given permission to receive emails, and that the list was collected in line with the privacy and marketing laws that apply to them. The Agency will use the subscriber list only to deliver the services in this agreement and will not sell, share or reuse it for any other purpose.',
        '5. Ownership of content. Once paid for, all newsletter content published for the Client belongs to the Client. The Client may reuse, edit and republish it freely. The Agency keeps ownership of its own internal templates, processes and tools, and may describe the general nature of the work in its portfolio unless the Client asks it not to.',
        '6. Confidentiality. Both parties agree to keep confidential any private business information shared during this agreement, including subscriber details, plans, pricing and results. This duty continues after the agreement ends. It does not apply to information that is already public or that must be disclosed by law.',
        '7. Term and cancellation. This agreement continues month to month from the start date until either party ends it. Either party may cancel by giving {notice_days} days\' written notice by email. Fees for the notice period remain payable, and the Agency will complete any newsletters already scheduled during that period. On cancellation, the Agency will return or hand over access to all Client content and subscriber data.',
        '8. Limitation of liability. The Agency will carry out the services with reasonable skill and care. The Agency does not guarantee any particular number of readers, opens, sales or other results. Neither party will be liable for indirect or unforeseeable losses. The Agency\'s total liability under this agreement is limited to the fees paid by the Client in the three months before the claim arose.',
        '9. General. This agreement is the full understanding between the parties about these services. Any changes must be agreed in writing, including by email. If any part of this agreement is found to be unenforceable, the rest will still apply.',
        'Signed for and on behalf of {agency_name}:'."\n".'Name: ____________________'."\n".'Signature: ____________________'."\n".'Date: ____________________',
        'Signed for and on behalf of {client_name}:'."\n".'Name: ____________________'."\n".'Signature: ____________________'."\n".'Date: ____________________',
    ]),

    'pricing_tips' => [
        'Price by the value of a steady customer relationship, not just by the hours it takes to write a newsletter.',
        'Start with a simple package and a clear number of newsletters so clients know exactly what they are paying for.',
        'Charge setup work, such as naming and sign-up pages, as a one-off fee or include it in the first month.',
        'Review your prices every six months and raise them for new clients once you have results to show.',
        'Ask for payment in advance each month so you are never chasing invoices for work already delivered.',
    ],

];
