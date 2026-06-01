<?php
/**
 * Edit profile form body (included by al_profileupdate.php).
 * Expects: $user, pv(), pr_checked(), $photo_src, $initials, $fullname, $program, $campus, $year_grad, $status, $ringR, $ringCirc, $ringOffset, $profileCompletion, $birthday_display, $today_max, $age_display, $lic_exam_text, $existing_sig, and related vars.
 */
if (!function_exists('pv')) {
    function pv(string $key, string $default = ''): string {
        global $user;
        return alumni_profile_form_val($user, $key, $default);
    }
}
?>
	<!-- profile-edit-form-start -->
	<form method="POST" enctype="multipart/form-data" id="profileForm" action="al_profileupdate.php">
		<input type="hidden" name="resume_parse_data" id="resumeParsedJSON" value="" />
		<input type="hidden" name="signature_data" id="signatureData" value="" />

		<div class="profile-hero">
			<div class="hero-inner">
				<div class="hero-left">
					<label for="photo" class="avatar-ring" title="Click to change photo">
						<div class="avatar-inner" id="photoPreviewWrap">
							<?php if ($photo_src !== '') : ?>
								<img src="<?php echo htmlspecialchars($photo_src, ENT_QUOTES, 'UTF-8'); ?>" alt="Photo" id="photoPreviewImg"
									onerror="this.style.display='none';var el=document.getElementById('photoInitials');if(el)el.style.display='flex';" />
								<span class="avatar-initials" id="photoInitials" style="<?php echo $photo_src ? 'display:none;' : ''; ?>"><?php echo htmlspecialchars($initials !== '' ? $initials : '?', ENT_QUOTES, 'UTF-8'); ?></span>
							<?php else : ?>
								<span class="avatar-initials" id="photoInitials"><?php echo htmlspecialchars($initials !== '' ? $initials : '?', ENT_QUOTES, 'UTF-8'); ?></span>
							<?php endif; ?>
						</div>
						<span class="hero-cam"><i class="fas fa-camera"></i></span>
					</label>
					<input type="file" name="photo" id="photo" accept="image/*" style="display:none;" />
					<div id="photoError" style="font-size:.78rem;color:var(--gold-light);margin-top:4px;"></div>
					<div>
						<div class="hero-eyebrow">Editing alumni record</div>
						<div class="hero-name"><?php echo htmlspecialchars($fullname !== '' ? $fullname : 'Your Name', ENT_QUOTES, 'UTF-8'); ?></div>
						<div class="hero-program"><?php echo htmlspecialchars($program !== '' ? $program : 'Program not set', ENT_QUOTES, 'UTF-8'); ?></div>
						<div class="hero-meta">
							<?php if ($campus !== '') : ?><span class="hero-chip"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($campus, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
							<?php if ($year_grad !== '') : ?><span class="hero-chip"><i class="fas fa-graduation-cap"></i>Class of <?php echo htmlspecialchars($year_grad, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
							<span class="hero-chip"><i class="fas fa-circle" style="font-size:.45rem;"></i><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
						</div>
					</div>
				</div>
				<div class="hero-right">
					<div class="completion-label">Profile Complete</div>
					<div class="completion-ring">
						<svg width="80" height="80" viewBox="0 0 80 80" aria-hidden="true">
							<circle class="completion-ring-bg" cx="40" cy="40" r="<?php echo (string) $ringR; ?>" />
							<circle class="completion-ring-fill" id="progressRingFill" cx="40" cy="40" r="<?php echo (string) $ringR; ?>"
								stroke-dasharray="<?php echo htmlspecialchars((string) $ringCirc, ENT_QUOTES, 'UTF-8'); ?>"
								stroke-dashoffset="<?php echo htmlspecialchars((string) $ringOffset, ENT_QUOTES, 'UTF-8'); ?>" />
						</svg>
						<div class="completion-pct" id="progressPct"><?php echo (int) $profileCompletion; ?>%</div>
					</div>
				</div>
			</div>
		</div>

		<div class="stats-row">
			<div class="stat-card"><div class="stat-icon"><i class="fas fa-id-badge"></i></div><div><div class="stat-val"><?php echo pv('student_number') ?: '—'; ?></div><div class="stat-lbl">Alumni ID</div></div></div>
			<div class="stat-card"><div class="stat-icon"><i class="fas fa-briefcase"></i></div><div><div class="stat-val"><?php echo pv('employment_status') ?: '—'; ?></div><div class="stat-lbl">Employment</div></div></div>
			<div class="stat-card"><div class="stat-icon"><i class="fas fa-building"></i></div><div><div class="stat-val" style="font-size:.95rem;"><?php echo pv('company') ?: '—'; ?></div><div class="stat-lbl">Company</div></div></div>
			<div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-alt"></i></div><div><div class="stat-val"><?php echo pv('year_graduated') ?: '—'; ?></div><div class="stat-lbl">Year Graduated</div></div></div>
		</div>

		<div class="resume-card">
			<h3><i class="fas fa-file-alt" style="margin-right:6px;color:var(--gold);"></i>Auto-fill from resume</h3>
			<p>Upload PDF or DOCX to pre-fill fields. Review each tab before saving.</p>
			<div class="resume-drop" id="resumeDrop">
				<input type="file" id="resumeInput" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" style="display:none;" />
				<i class="fas fa-cloud-upload-alt"></i>
				<div style="font-size:.88rem;font-weight:600;color:var(--ink);">Drag &amp; drop resume here</div>
				<div style="font-size:.75rem;color:var(--ink-muted);margin-top:3px;">PDF or DOCX · Max 10 MB</div>
			</div>
			<div class="resume-status loading" id="resumeLoading"><i class="fas fa-spinner fa-spin"></i> Parsing…</div>
			<div class="resume-status success" id="resumeSuccess"><i class="fas fa-check-circle"></i> <span id="resumeSuccessText">Fields updated — review tabs and save.</span></div>
			<div class="resume-status error" id="resumeError"><i class="fas fa-exclamation-circle"></i> <span id="resumeErrorText"></span></div>
		</div>

		<div class="tab-bar" role="tablist">
			<button type="button" class="tab-btn active" role="tab" onclick="switchTab('personal',this)"><i class="fas fa-user"></i><span class="tab-label"> Personal</span></button>
			<button type="button" class="tab-btn" role="tab" onclick="switchTab('academic',this)"><i class="fas fa-graduation-cap"></i><span class="tab-label"> Academic</span></button>
			<button type="button" class="tab-btn" role="tab" onclick="switchTab('employment',this)"><i class="fas fa-briefcase"></i><span class="tab-label"> Employment</span></button>
			<button type="button" class="tab-btn" role="tab" onclick="switchTab('feedback',this)"><i class="fas fa-clipboard-check"></i><span class="tab-label"> Feedback</span></button>
			<button type="button" class="tab-btn" role="tab" onclick="switchTab('signature',this)"><i class="fas fa-pen-fancy"></i><span class="tab-label"> Signature</span></button>
		</div>

		<div id="tab-personal" class="tab-panel active">
		<div class="section-grid">
			<div class="card" id="sec-personal">
				<div class="card-head"><div class="card-icon"><i class="fas fa-user"></i></div><div class="card-title">Personal Information</div></div>
				<div class="card-rule"></div>
				<div class="field-grid">
					<div class="field-item"><label class="field-label">First Name <span class="req">*</span></label><input type="text" name="firstname" class="form-input" required value="<?php echo pv('firstname'); ?>" /></div>
					<div class="field-item"><label class="field-label">Last Name <span class="req">*</span></label><input type="text" name="lastname" class="form-input" required value="<?php echo pv('lastname'); ?>" /></div>
					<div class="field-item"><label class="field-label">Middle Name</label><input type="text" name="middlename" class="form-input" value="<?php echo pv('middlename'); ?>" /></div>
					<div class="field-item"><label class="field-label">Name Extension</label><input type="text" name="name_ext" class="form-input" value="<?php echo pv('name_ext'); ?>" /></div>
					<div class="field-item">
						<label class="field-label">Birthday <span class="req">*</span></label>
						<input type="date" name="birthday" id="birthday" class="form-input" required max="<?php echo htmlspecialchars($today_max, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($birthday_display, ENT_QUOTES, 'UTF-8'); ?>" onchange="updateAgeFromBirthday()" oninput="updateAgeFromBirthday()" />
						<div class="form-hint">Age updates from birthday (same as registration).</div>
					</div>
					<div class="field-item">
						<label class="field-label">Age <span class="req">*</span></label>
						<input type="number" name="age" id="age" class="form-input" min="0" max="120" value="<?php echo htmlspecialchars($age_display, ENT_QUOTES, 'UTF-8'); ?>" readonly tabindex="-1" title="From birthday" />
						<div class="age-sync-note"><i class="fas fa-circle-check"></i> From birthday</div>
					</div>
					<div class="field-item"><label class="field-label">Gender <span class="req">*</span></label>
						<select name="gender" class="form-select" required>
							<option value="">Select gender</option>
							<option value="Male" <?php echo (($user['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
							<option value="Female" <?php echo (($user['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
							<option value="Other" <?php echo (($user['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
						</select>
					</div>
					<div class="field-item"><label class="field-label">Civil Status <span class="req">*</span></label>
						<select name="civil_status" class="form-select" required>
							<option value="">Select status</option>
							<?php foreach (['Single', 'Married', 'Widowed', 'Separated', 'Divorced'] as $cs) : ?>
							<option value="<?php echo htmlspecialchars($cs, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($user['civil_status'] ?? '') === $cs) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cs, ENT_QUOTES, 'UTF-8'); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field-item"><label class="field-label">Religion</label><input type="text" name="religion" class="form-input" value="<?php echo pv('religion'); ?>" /></div>
					<div class="field-item"><label class="field-label">Nationality</label><input type="text" name="nationality" class="form-input" value="<?php echo pv('nationality', 'Filipino'); ?>" /></div>
				</div>
			</div>
			<div class="card" id="sec-contact">
				<div class="card-head"><div class="card-icon"><i class="fas fa-address-card"></i></div><div class="card-title">Contact Information</div></div>
				<div class="card-rule"></div>
				<div class="field-grid">
					<div class="field-item full"><label class="field-label">Email <span class="req">*</span></label><input type="email" name="email" class="form-input" required value="<?php echo pv('email'); ?>" /></div>
					<div class="field-item full"><label class="field-label">Address <span class="req">*</span></label><textarea name="address" class="form-textarea" required><?php echo pv('address'); ?></textarea></div>
					<div class="field-item"><label class="field-label">Personal Contact</label><input type="text" name="personal_contact" class="form-input" value="<?php echo pv('personal_contact'); ?>" /></div>
					<div class="field-item"><label class="field-label">Emergency Contact</label><input type="text" name="emergency_contact" class="form-input" value="<?php echo pv('emergency_contact'); ?>" /></div>
				</div>
			</div>
		</div>
		</div>

		<div id="tab-academic" class="tab-panel">
			<div class="card" id="sec-academic">
				<div class="card-head"><div class="card-icon"><i class="fas fa-graduation-cap"></i></div><div class="card-title">Academic Information</div></div>
				<div class="card-rule"></div>
				<div class="field-grid">
					<div class="field-item"><label class="field-label">Student / Alumni ID <span class="req">*</span></label><input type="text" name="student_number" class="form-input" required value="<?php echo pv('student_number'); ?>" /></div>
					<div class="field-item"><label class="field-label">Campus <span class="req">*</span></label><input type="text" name="campus" class="form-input" required value="<?php echo pv('campus', 'Antipolo City'); ?>" /></div>
					<div class="field-item"><label class="field-label">College <span class="req">*</span></label>
						<select name="college" class="form-select" required>
							<option value="">Select college</option>
							<?php
							$colleges = [
								'College of Computer Studies',
								'College of Engineering',
								'College of Business & Accountancy',
								'College of Arts and Sciences',
								'College of Education',
								'College of Nursing',
								'College of Medicine',
							];
							$cur_college = trim((string) ($user['college'] ?? ''));
							foreach ($colleges as $c) :
								?>
							<option value="<?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($cur_college === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></option>
							<?php endforeach; ?>
							<?php if ($cur_college !== '' && !in_array($cur_college, $colleges, true)) : ?>
							<option value="<?php echo htmlspecialchars($cur_college, ENT_QUOTES, 'UTF-8'); ?>" selected><?php echo htmlspecialchars($cur_college, ENT_QUOTES, 'UTF-8'); ?></option>
							<?php endif; ?>
						</select>
					</div>
					<div class="field-item full"><label class="field-label">Degree / Program <span class="req">*</span></label><input type="text" name="program" class="form-input" required value="<?php echo pv('program'); ?>" /></div>
					<div class="field-item"><label class="field-label">Month Graduated</label>
						<select name="month_graduated" class="form-select">
							<option value="">Select month</option>
							<?php
							$months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
							$mg = (string) ($user['month_graduated'] ?? '');
							foreach ($months as $k => $v) :
								?>
							<option value="<?php echo htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($mg === $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field-item"><label class="field-label">Year Graduated <span class="req">*</span></label><input type="text" name="year_graduated" class="form-input" required value="<?php echo pv('year_graduated'); ?>" /></div>
					<div class="field-item full"><label class="field-label">Club / Organization Involvement</label><input type="text" name="club_involvement" class="form-input" value="<?php echo pv('club_involvement'); ?>" placeholder="e.g. ICpEP, IATD, SSG…" /></div>
				</div>
			</div>
			<div class="card" id="sec-licensure" style="margin-top:1.25rem;">
				<div class="card-head"><div class="card-icon"><i class="fas fa-certificate"></i></div><div class="card-title">Licensure &amp; Post-Graduate</div></div>
				<div class="card-rule"></div>
				<div class="field-grid">
					<div class="field-item full">
						<label class="field-label">Did you pass a Licensure Examination?</label>
						<div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
							<label class="check-row" style="padding:0;"><input type="radio" name="licensure_passed" value="yes" <?php echo pr_checked('licensure_exam', 'yes') ? 'checked' : ''; ?> /> Yes, I passed</label>
							<label class="check-row" style="padding:0;"><input type="radio" name="licensure_passed" value="no" <?php echo pr_checked('licensure_exam', 'no') ? 'checked' : ''; ?> /> No, I didn't</label>
							<label class="check-row" style="padding:0;"><input type="radio" name="licensure_passed" value="not_applicable" <?php echo pr_checked('licensure_exam', 'not_applicable') ? 'checked' : ''; ?> /> Not applicable — no licensure exam in my course</label>
							<label class="check-row" style="padding:0;"><input type="radio" name="licensure_passed" value="not_yet" <?php echo pr_checked('licensure_exam', 'not_yet') ? 'checked' : ''; ?> /> Not yet, but I plan to take it in the future</label>
						</div>
					</div>
					<div class="field-item full"><label class="field-label">Licensure Exam Name (optional)</label><input type="text" name="licensure_exam" class="form-input" value="<?php echo htmlspecialchars($lic_exam_text, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. PRC Board Exam, Bar Exam" /></div>
					<div class="field-item full">
						<label class="field-label">Enrolled in another degree or Masteral studies?</label>
						<div style="display:flex;flex-direction:column;gap:8px;margin-top:6px;">
							<label class="check-row" style="padding:0;"><input type="radio" name="post_grad" value="yes" <?php echo pr_checked('post_grad', 'yes') ? 'checked' : ''; ?> /> Yes</label>
							<label class="check-row" style="padding:0;"><input type="radio" name="post_grad" value="no" <?php echo pr_checked('post_grad', 'no') ? 'checked' : ''; ?> /> No</label>
							<label class="check-row" style="padding:0;"><input type="radio" name="post_grad" value="not_applicable" <?php echo pr_checked('post_grad', 'not_applicable') ? 'checked' : ''; ?> /> Not applicable — I'm still a graduating student</label>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div id="tab-employment" class="tab-panel">
			<div class="card" id="sec-employment">
				<div class="card-head"><div class="card-icon"><i class="fas fa-briefcase"></i></div><div class="card-title">Employment Details</div></div>
				<div class="card-rule"></div>
				<div class="field-grid">
					<div class="field-item"><label class="field-label">Employment Status</label>
						<select name="employment_status" class="form-select">
							<option value="">Select status</option>
							<?php foreach (['Employed', 'Self-Employed', 'Unemployed', 'Student', 'Prefer not to say', 'Underemployed', 'Retired'] as $es) : ?>
							<option value="<?php echo htmlspecialchars($es, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($user['employment_status'] ?? '') === $es) ? 'selected' : ''; ?>><?php echo htmlspecialchars($es, ENT_QUOTES, 'UTF-8'); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="field-item"><label class="field-label">Industry</label><input type="text" name="industry" class="form-input" value="<?php echo pv('industry'); ?>" /></div>
					<div class="field-item"><label class="field-label">Current Company</label><input type="text" name="company" class="form-input" value="<?php echo pv('company'); ?>" placeholder="N/A if not employed" /></div>
					<div class="field-item"><label class="field-label">Current Position</label><input type="text" name="position" class="form-input" value="<?php echo pv('position'); ?>" /></div>
					<div class="field-item"><label class="field-label">Length of Service</label><input type="text" name="length_of_service" class="form-input" value="<?php echo pv('length_of_service'); ?>" /></div>
					<div class="field-item"><label class="field-label">Previous Role</label><input type="text" name="previous_role" class="form-input" value="<?php echo pv('previous_role'); ?>" /></div>
					<div class="field-item full"><label class="field-label">Skills</label><textarea name="skills" class="form-textarea" placeholder="Comma-separated skills"><?php echo pv('skills'); ?></textarea></div>
					<div class="field-item full"><label class="field-label">Employment History</label><textarea name="employment_history" class="form-textarea"><?php echo pv('employment_history'); ?></textarea></div>
					<div class="field-item full" style="border-top:1px solid var(--cream-dark);padding-top:1rem;">
						<label class="check-row"><input type="checkbox" name="employment_private" value="1" <?php echo !empty($user['employment_private']) ? 'checked' : ''; ?> /><span>Keep employment details <strong>private</strong> (admin/tracer only).</span></label>
						<label class="check-row"><input type="checkbox" name="data_privacy_consent" value="1" <?php echo !empty($user['data_privacy_consent_at']) ? 'checked' : ''; ?> /><span>I consent under the <strong>Data Privacy Act of 2012</strong> when providing employment data.</span></label>
					</div>
				</div>
			</div>
		</div>

		<div id="tab-feedback" class="tab-panel">
			<div class="card">
				<div class="card-head"><div class="card-icon"><i class="fas fa-clipboard-check"></i></div><div class="card-title">Career &amp; Alumni Feedback</div></div>
				<div class="card-rule"></div>
				<div class="field-grid">
					<div class="field-item full">
						<label class="field-label">Months to get your first job?</label>
						<div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:8px;">
							<?php foreach (['less_than_1' => '< 1 month', '1_to_3' => '1–3 months', '4_to_6' => '4–6 months', 'more_than_6' => '> 6 months'] as $mv => $ml) : ?>
							<label class="check-row" style="padding:0;"><input type="radio" name="months_to_get_job" value="<?php echo htmlspecialchars($mv, ENT_QUOTES, 'UTF-8'); ?>" <?php echo pr_checked('months_to_get_job', $mv) ? 'checked' : ''; ?> /> <?php echo htmlspecialchars($ml, ENT_QUOTES, 'UTF-8'); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="field-item full">
						<label class="field-label">My job is aligned with my degree</label>
						<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
							<?php foreach (['strongly_agree' => 'Strongly Agree', 'agree' => 'Agree', 'neutral' => 'Neutral', 'disagree' => 'Disagree', 'strongly_disagree' => 'Strongly Disagree'] as $jv => $jl) : ?>
							<label class="check-row" style="padding:0;"><input type="radio" name="job_aligned" value="<?php echo htmlspecialchars($jv, ENT_QUOTES, 'UTF-8'); ?>" <?php echo pr_checked('job_aligned', $jv) ? 'checked' : ''; ?> /> <?php echo htmlspecialchars($jl, ENT_QUOTES, 'UTF-8'); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="field-item full">
						<label class="field-label">College prepared me well for my career</label>
						<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
							<?php foreach (['strongly_agree' => 'Strongly Agree', 'agree' => 'Agree', 'neutral' => 'Neutral', 'disagree' => 'Disagree', 'strongly_disagree' => 'Strongly Disagree'] as $jv => $jl) : ?>
							<label class="check-row" style="padding:0;"><input type="radio" name="college_prepared" value="<?php echo htmlspecialchars($jv, ENT_QUOTES, 'UTF-8'); ?>" <?php echo pr_checked('college_prepared', $jv) ? 'checked' : ''; ?> /> <?php echo htmlspecialchars($jl, ENT_QUOTES, 'UTF-8'); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="field-item full">
						<label class="field-label">I am proud to be an OLFU alumni</label>
						<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">
							<?php foreach (['strongly_agree' => 'Strongly Agree', 'agree' => 'Agree', 'neutral' => 'Neutral', 'disagree' => 'Disagree', 'strongly_disagree' => 'Strongly Disagree'] as $jv => $jl) : ?>
							<label class="check-row" style="padding:0;"><input type="radio" name="proud_alumni" value="<?php echo htmlspecialchars($jv, ENT_QUOTES, 'UTF-8'); ?>" <?php echo pr_checked('proud_alumni', $jv) ? 'checked' : ''; ?> /> <?php echo htmlspecialchars($jl, ENT_QUOTES, 'UTF-8'); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="field-item full"><label class="field-label">Important Soft Skill</label><input type="text" name="important_soft_skill" class="form-input" value="<?php echo pv('important_soft_skill'); ?>" /></div>
				</div>
			</div>
		</div>

		<div id="tab-signature" class="tab-panel">
			<div class="card" id="sec-signature">
				<div class="card-head"><div class="card-icon"><i class="fas fa-pen-fancy"></i></div><div class="card-title">Digital Signature</div></div>
				<div class="card-rule"></div>
				<p style="font-size:.85rem;color:var(--ink-soft);margin-bottom:14px;">
					Your signature appears on the <strong>Card Holder's Signature</strong> field of your alumni ID.
					Draw a new one below, or keep your existing signature. Click <strong>Save Signature</strong> to store it immediately
					without submitting the full form.
				</p>
				<div class="sig-section">
					<div class="sig-tabs">
						<button type="button" class="sig-tab active" onclick="switchSigTab('draw',this)"><i class="fas fa-pen" style="margin-right:6px;"></i>Draw New</button>
						<button type="button" class="sig-tab" onclick="switchSigTab('existing',this)"><i class="fas fa-image" style="margin-right:6px;"></i>Current Signature</button>
					</div>
					<div class="sig-tab-panel active" id="sig-draw-panel">
						<div class="sig-canvas-wrap" id="sigCanvasWrap">
							<canvas id="sigPad" width="600" height="130"></canvas>
							<div class="sig-hint-overlay" id="sigHint"><span>Sign here with mouse or touch</span></div>
						</div>
						<div class="sig-footer-row">
							<span style="font-size:.75rem;color:var(--ink-muted);">Use a thin, smooth stroke for best results</span>
							<div style="display:flex;gap:8px;">
								<button type="button" id="sigClear" class="btn btn-outline btn-sm"><i class="fas fa-eraser"></i> Clear</button>
								<button type="button" id="sigSaveNow" class="btn btn-forest btn-sm"><i class="fas fa-save"></i> Save Signature</button>
							</div>
						</div>
						<div class="sig-saved-toast" id="sigSavedToast">
							<i class="fas fa-check-circle"></i>
							Signature saved! It will now appear on your <a href="alumni_id_card.php" style="color:var(--forest);font-weight:700;text-decoration:underline;">ID card</a>.
						</div>
					</div>
					<div class="sig-tab-panel" id="sig-existing-panel">
						<p style="font-size:.82rem;color:var(--ink-muted);margin-bottom:10px;">Your currently saved signature:</p>
						<div class="sig-preview-wrap">
							<?php if ($existing_sig !== '') : ?>
								<img src="<?php echo htmlspecialchars($existing_sig, ENT_QUOTES, 'UTF-8'); ?>" alt="Current signature" id="existingSigImg">
							<?php else : ?>
								<span class="no-sig">No signature saved yet — use the Draw tab to create one.</span>
							<?php endif; ?>
						</div>
						<?php if ($existing_sig !== '') : ?>
						<p style="margin-top:8px;font-size:.78rem;color:var(--ink-muted);">
							To replace it, switch to the <strong>Draw New</strong> tab and save a new signature.
						</p>
						<?php endif; ?>
					</div>
				</div>
				<p style="margin-top:12px;font-size:.78rem;color:var(--ink-muted);">
					<a href="alumni_id_card.php" style="color:var(--forest-mid);text-decoration:underline;text-underline-offset:2px;">
						<i class="fas fa-id-card" style="margin-right:4px;"></i>Preview your ID card →
					</a>
				</p>
			</div>
		</div>

		<div class="save-bar">
			<div class="save-bar-left">Profile completion: <strong id="saveBarPct"><?php echo (int) $profileCompletion; ?>%</strong></div>
			<div class="save-bar-right">
				<a href="al_profile.php" class="btn btn-outline" id="cancelToProfileBtn" style="border-color:rgba(255,255,255,.35);color:rgba(255,255,255,.85);">Cancel</a>
				<button type="submit" id="saveBtn" class="btn btn-gold"><i class="fas fa-save"></i> Save Changes</button>
			</div>
		</div>
	</form>
	<!-- profile-edit-form-end -->
