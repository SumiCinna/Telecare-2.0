<?php
// includes/data.php
// Static stand-in data for the Legal Policies workflow. This is a frontend-first
// build — swap this for real $conn queries once the backend is wired up.

$policies = [
	1 => [
		'title'       => 'Data Policy',
		'type'        => 'Data & Privacy',
		'pill'        => 'pill-blue',
		'icon_bg'     => 'var(--blue-light)', 'icon_fg' => 'var(--blue)',
		'version'     => 'v1.2',
		'updated'     => 'Aug 15, 2026',
		'updated_by'  => 'Super Admin',
		'status'      => 'Published',
		'desc'        => 'Guidelines for data collection, usage, and protection.',
		'short_desc'  => 'Guidelines for data collection, usage, and protection.',
		'effective_date' => 'Aug 15, 2026',
	],
	2 => [
		'title'       => 'Privacy Policy',
		'type'        => 'Data & Privacy',
		'pill'        => 'pill-blue',
		'icon_bg'     => 'var(--blue-light)', 'icon_fg' => 'var(--blue)',
		'version'     => 'v1.3',
		'updated'     => 'Aug 10, 2026',
		'updated_by'  => 'Super Admin',
		'status'      => 'Published',
		'desc'        => 'Details on how user information is collected, used, and secured.',
		'short_desc'  => 'Details how user information is collected, used, and secured.',
		'effective_date' => 'Aug 10, 2026',
	],
	3 => [
		'title'       => 'Terms of Use and Agreement',
		'type'        => 'Terms & Agreements',
		'pill'        => 'pill-purple',
		'icon_bg'     => 'var(--purple-light)', 'icon_fg' => 'var(--purple)',
		'version'     => 'v1.1',
		'updated'     => 'Jul 28, 2026',
		'updated_by'  => 'Super Admin',
		'status'      => 'Published',
		'desc'        => 'Rules and conditions for using the platform.',
		'short_desc'  => 'Rules and conditions for using the platform.',
		'effective_date' => 'Jul 28, 2026',
	],
	4 => [
		'title'       => 'Payment Policy',
		'type'        => 'Payments',
		'pill'        => 'pill-orange',
		'icon_bg'     => 'var(--orange-light)', 'icon_fg' => 'var(--orange)',
		'version'     => 'v1.0',
		'updated'     => 'Jul 20, 2026',
		'updated_by'  => 'Super Admin',
		'status'      => 'Published',
		'desc'        => 'Policies for payments, refunds, and transactions.',
		'short_desc'  => 'Policies for payments, refunds, and transactions.',
		'effective_date' => 'Jul 20, 2026',
	],
];

// Version history, keyed by policy id
$policy_versions = [
	2 => [
		['version' => 'v1.3', 'date' => 'Aug 10, 2026', 'by' => 'Super Admin', 'status' => 'Published', 'current' => true],
		['version' => 'v1.2', 'date' => 'Jul 15, 2026', 'by' => 'Super Admin', 'status' => 'Published', 'current' => false],
		['version' => 'v1.1', 'date' => 'Jun 20, 2026', 'by' => 'Super Admin', 'status' => 'Published', 'current' => false],
		['version' => 'v1.0', 'date' => 'May 1, 2026',  'by' => 'Super Admin', 'status' => 'Archived',  'current' => false],
	],
];

// Change summary for the latest revision, keyed by policy id
$policy_changes = [
	2 => [
		'from' => 'v1.2', 'to' => 'v1.3', 'date' => 'Aug 10, 2026, 11:20 AM',
		'items' => [
			['title' => 'Added Section 1.9: Telehealth Encryption Protocols', 'copy' => 'Complies with Data Privacy Act of 2012 (RA 10173) and end-to-end WebRTC security mandates.'],
			['title' => 'Updated Data Retention Period', 'copy' => 'Electronic Medical Records (EMR) retention specified to mandatory minimum of 10 years per clinical liability.'],
			['title' => 'Super Admin Signature Attached', 'copy' => 'Digitally countersigned by Compliance Officer (ID: 4CO-8212).'],
		],
	],
];

function policy_lookup(array $policies, $id) {
	$id = (int) $id;
	return $policies[$id] ?? null;
}