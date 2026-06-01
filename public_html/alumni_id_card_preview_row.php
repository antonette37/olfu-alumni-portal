<?php
/**
 * Build the $card array for render_alumni_id_cards() from an itcp row.
 * Same rules as ad_alumni_id_check.php (validity = DECEMBER year+3 only; no verified_alumni).
 *
 * @param array<string, mixed> $row itcp associative row (any key casing from mysqli)
 * @param bool $includeSignature If true, set signatureSrc when a safe file exists under uploads/signatures/
 * @return array<string, string>
 */
if (!function_exists('mb_strtoupper')) {
	function mb_strtoupper($string, $encoding = null)
	{
		return strtoupper((string) $string);
	}
}
if (!function_exists('mb_substr')) {
	function mb_substr($str, $start, $length = null, $encoding = null)
	{
		$str = (string) $str;
		return $length === null ? substr($str, (int) $start) : substr($str, (int) $start, (int) $length);
	}
}

if (!function_exists('alumni_id_card_row_to_render_array')) {
function alumni_id_card_row_to_render_array(array $row, bool $includeSignature = false): array
{
	$fn = trim((string) ($row['firstname'] ?? ''));
	$mi = trim((string) ($row['middlename'] ?? ''));
	$ln = trim((string) ($row['lastname'] ?? ''));
	$ext = trim((string) ($row['name_ext'] ?? ''));
	$mid = '';
	if ($mi !== '') {
		$mid = (strlen($mi) <= 3 && strpos($mi, ' ') === false) ? $mi . '.' : $mi;
	}
	$parts = array_filter([$fn, $mid, $ln, $ext]);
	$fullName = mb_strtoupper(implode(' ', $parts), 'UTF-8');

	$d = preg_replace('/\D/', '', (string) ($row['student_number'] ?? ''));
	$cardFormatted = '';
	if ($d !== '') {
		$d = str_pad(substr($d, 0, 16), 16, '0');
		$cardFormatted = trim(chunk_split($d, 4, ' '));
	}
	if ($cardFormatted === '') {
		$cardFormatted = 'PENDING — NO STUDENT NO.';
	}

	$program = trim((string) ($row['program'] ?? ''));
	if ($program === '') {
		$program = '—';
	}

	$batchYear = trim((string) ($row['year_graduated'] ?? ''));
	if ($batchYear === '') {
		$batchYear = '—';
	}

	$yg = $row['year_graduated'] ?? null;
	$validUntil = '';
	if ($yg !== null && (string) $yg !== '') {
		$y = (int) preg_replace('/\D/', '', (string) $yg);
		if ($y >= 1990 && $y <= 2100) {
			$validUntil = 'DECEMBER ' . (string) ($y + 3);
		}
	}
	if ($validUntil === '') {
		$validUntil = '—';
	}

	$address = trim((string) ($row['address'] ?? ''));
	if ($address === '') {
		$address = '—';
	}
	$contact = trim((string) ($row['personal_contact'] ?? ''));
	if ($contact === '') {
		$contact = '—';
	}
	$emergency = trim((string) ($row['emergency_contact'] ?? ''));
	if ($emergency === '') {
		$emergency = '—';
	}

	$photo = trim((string) ($row['photo'] ?? ''));
	$photoSrc = '';
	if ($photo !== '') {
		if (strpos($photo, 'http') === 0) {
			$photoSrc = $photo;
		} else {
			$photoSrc = 'serve_profile_image.php?img=' . rawurlencode(basename($photo));
		}
	}

	$fn0 = trim((string) ($row['firstname'] ?? ''));
	$ln0 = trim((string) ($row['lastname'] ?? ''));
	if ($fn0 !== '' || $ln0 !== '') {
		$idInitials = strtoupper(mb_substr($fn0, 0, 1, 'UTF-8') . mb_substr($ln0, 0, 1, 'UTF-8'));
	} else {
		$idInitials = '?';
	}

	$out = [
		'photoSrc' => $photoSrc,
		'idInitials' => $idInitials,
		'fullName' => $fullName,
		'cardFormatted' => $cardFormatted,
		'program' => $program,
		'batchYear' => $batchYear,
		'validUntil' => $validUntil,
		'address' => $address,
		'contact' => $contact,
		'emergency' => $emergency,
		'signatureSrc' => '',
	];

	if ($includeSignature) {
		$sig = trim(str_replace("\0", '', (string) ($row['signature_path'] ?? '')));
		$sig = str_replace('\\', '/', $sig);
		$rel = '';
		if ($sig !== '' && strpos($sig, '..') === false) {
			$sig = ltrim($sig, '/');
			if (stripos($sig, 'signatures/') === 0) {
				$ok = true;
				foreach (explode('/', $sig) as $seg) {
					if ($seg === '' || $seg === '.' || $seg === '..') {
						$ok = false;
						break;
					}
				}
				if ($ok) {
					$root = dirname(__DIR__);
					$full = realpath($root . '/uploads/' . str_replace('/', DIRECTORY_SEPARATOR, $sig));
					$base = realpath($root . '/uploads');
					if ($full && $base && is_file($full) && stripos($full, $base) === 0) {
						$ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
						if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
							$rel = 'uploads/' . str_replace('\\', '/', $sig);
						}
					}
				}
			}
		}
		$out['signatureSrc'] = $rel;
	}

	return $out;
}
}
