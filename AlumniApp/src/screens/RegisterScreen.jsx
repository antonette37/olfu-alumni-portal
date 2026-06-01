import { useState } from "react";
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  Switch,
  Alert,
} from "react-native";
import * as ImagePicker from "expo-image-picker";
import { T } from "../constants/colors";
import { PROGRAMS, STEP_LABELS } from "../constants/mockData";
import StepIndicator from "../components/StepIndicator";
import IdUploadCard from "../components/IdUploadCard";
import Input from "../components/Input";
import Select from "../components/Select";
import PrimaryBtn from "../components/PrimaryBtn";
import { register } from "../api/auth";

const INITIAL = {
  idFrontUri: null,
  idFrontName: "",
  idBackUri: null,
  idBackName: "",
  alumni_id_number: "",
  profilePhotoUri: null,
  profile_photo_name: "",
  lastname: "",
  firstname: "",
  middleInitial: "",
  campus: "Antipolo City",
  year_graduated: "",
  month_graduated: "",
  college: "",
  degree: "",
  birthday: "",
  gender: "",
  civil_status: "",
  nationality: "Filipino",
  religion: "",
  personal_email: "",
  personal_contact: "",
  emergency_contact: "",
  address: "",
  passed_licensure: "",
  enrolled_post_grad: "",
  licensure_exam: "",
  club_involvement: "",
  employment_status: "",
  company: "",
  industry: "",
  position: "",
  length_of_service: "",
  months_to_get_job: "",
  college_prepared: "",
  proud_alumni: "",
  password: "",
  confirm_password: "",
  consent: false,
};

const LIKERT = ["Strongly Agree", "Agree", "Neutral", "Disagree", "Strongly Disagree"];
const MONTHS = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December",
].map((v) => ({ value: v, label: v }));

async function pickImage() {
  const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
  if (status !== "granted") {
    Alert.alert("Permission needed", "Allow photo access to upload your ID.");
    return null;
  }
  const result = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: ImagePicker.MediaTypeOptions.Images,
    quality: 0.8,
  });
  if (result.canceled || !result.assets?.[0]) return null;
  const asset = result.assets[0];
  return { uri: asset.uri, name: asset.fileName || `upload_${Date.now()}.jpg` };
}

function Likert({ value, onChange, error }) {
  return (
    <View style={styles.likertWrap}>
      <View style={styles.likertRow}>
        {LIKERT.map((o) => (
          <TouchableOpacity
            key={o}
            onPress={() => onChange(o)}
            style={[styles.chip, value === o && styles.chipOn]}
          >
            <Text style={[styles.chipText, value === o && styles.chipTextOn]}>{o}</Text>
          </TouchableOpacity>
        ))}
      </View>
      {error ? <Text style={styles.fieldErr}>⚠ {error}</Text> : null}
    </View>
  );
}

function RadioGroup({ options, value, onChange, error }) {
  return (
    <View>
      {options.map((o) => (
        <TouchableOpacity
          key={o.value}
          onPress={() => onChange(o.value)}
          style={[styles.radio, value === o.value && styles.radioOn]}
        >
          <View style={[styles.radioDot, value === o.value && styles.radioDotOn]} />
          <Text style={[styles.radioLabel, value === o.value && styles.radioLabelOn]}>{o.label}</Text>
        </TouchableOpacity>
      ))}
      {error ? <Text style={styles.fieldErr}>⚠ {error}</Text> : null}
    </View>
  );
}

export default function RegisterScreen({ route, navigation }) {
  const regType = route.params?.regType ?? "new";
  const isLegacy = regType === "legacy";
  const [step, setStep] = useState(0);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});
  const [form, setForm] = useState(INITIAL);

  const set = (k, v) => setForm((f) => ({ ...f, [k]: v }));
  const years = Array.from({ length: 30 }, (_, i) => {
    const y = new Date().getFullYear() - i;
    return { value: String(y), label: String(y) };
  });

  const validate = () => {
    const e = {};
    if (step === 0) {
      if (!form.idFrontName) e.idFront = "Required";
      if (!form.idBackName) e.idBack = "Required";
      if (!form.profile_photo_name) e.photo = "Required";
      if (!form.firstname.trim()) e.firstname = "Required";
      if (!form.lastname.trim()) e.lastname = "Required";
      if (!form.year_graduated) e.year_graduated = "Required";
      if (!form.college) e.college = "Required";
      if (!form.degree) e.degree = "Required";
      if (isLegacy) {
        const digits = (form.alumni_id_number || "").replace(/\D/g, "");
        if (digits.length < 8 || digits.length > 16) e.alumni_id_number = "Enter 8–16 digit Alumni ID number";
      }
    }
    if (step === 1) {
      if (!form.gender) e.gender = "Required";
      if (!form.civil_status) e.civil_status = "Required";
      if (!/^[^\s@]+@(gmail\.com|yahoo\.com)$/i.test(form.personal_email)) {
        e.personal_email = "Use @gmail.com or @yahoo.com only";
      }
      const digits = (form.personal_contact || "").replace(/\D/g, "");
      if (digits.length < 10 || digits.length > 11) e.personal_contact = "10–11 digits required";
      if (!form.address.trim()) e.address = "Required";
    }
    if (step === 2) {
      if (!form.passed_licensure) e.passed_licensure = "Please select an option";
      if (!form.enrolled_post_grad) e.enrolled_post_grad = "Please select an option";
    }
    if (step === 3) {
      if (!form.employment_status) e.employment_status = "Required";
      if (!form.college_prepared) e.college_prepared = "Please select an option";
      if (!form.proud_alumni) e.proud_alumni = "Please select an option";
    }
    if (step === 4) {
      if (!form.personal_email) e.account_email = "Go to Step 2 to add your email";
      if (form.password.length < 8) e.password = "At least 8 characters";
      if (form.password !== form.confirm_password) e.confirm_password = "Passwords do not match";
      if (!form.consent) e.consent = "You must agree to proceed";
    }
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const next = () => {
    if (validate()) setStep((s) => Math.min(s + 1, 4));
  };
  const prev = () => {
    setErrors({});
    setStep((s) => Math.max(s - 1, 0));
  };

  const handlePickId = async (side) => {
    const picked = await pickImage();
    if (!picked) return;
    if (side === "front") setForm((f) => ({ ...f, idFrontUri: picked.uri, idFrontName: picked.name }));
    else setForm((f) => ({ ...f, idBackUri: picked.uri, idBackName: picked.name }));
  };

  const handlePickPhoto = async () => {
    const picked = await pickImage();
    if (!picked) return;
    setForm((f) => ({ ...f, profilePhotoUri: picked.uri, profile_photo_name: picked.name }));
  };

  const buildFormData = () => {
    const fd = new FormData();
    const employed =
      form.employment_status === "Employed" || form.employment_status === "Self-employed";

    fd.append("firstname", form.firstname);
    fd.append("lastname", form.lastname);
    fd.append("middleInitial", form.middleInitial || "");
    fd.append("campus", form.campus || "Antipolo City");
    fd.append("yearGraduated", form.year_graduated);
    fd.append("month_graduated", form.month_graduated || "");
    fd.append("college", form.college);
    fd.append("degree", form.degree);
    fd.append("personalEmail", form.personal_email);
    fd.append("contactNumber", form.personal_contact);
    fd.append("gender", form.gender);
    fd.append("civilStatus", form.civil_status);
    fd.append("address", form.address);
    fd.append("emergency_contact", form.emergency_contact || "");
    fd.append("birthday", form.birthday || "");
    fd.append("religion", form.religion || "");
    fd.append("nationality", form.nationality || "Filipino");
    fd.append("passedLicensure", form.passed_licensure);
    fd.append("enrolledPostGrad", form.enrolled_post_grad);
    fd.append("currentlyEmployed", form.employment_status || "");
    fd.append("monthsToGetJob", form.months_to_get_job || "");
    fd.append("collegePrepared", form.college_prepared);
    fd.append("proudAlumni", form.proud_alumni);
    fd.append("licensure_exam", form.licensure_exam || "");
    fd.append("club_involvement", form.club_involvement || "");
    if (employed) {
      fd.append("company", form.company || "");
      fd.append("industry", form.industry || "");
      fd.append("position", form.position || "");
      fd.append("length_of_service", form.length_of_service || "");
    }
    fd.append(
      "alumniIdNumber",
      isLegacy ? form.alumni_id_number : form.alumni_id_number || ""
    );
    fd.append("email", form.personal_email);
    fd.append("password", form.password);
    if (form.consent) fd.append("consent", "1");

    if (form.profilePhotoUri) {
      fd.append("profile_photo", {
        uri: form.profilePhotoUri,
        name: form.profile_photo_name,
        type: "image/jpeg",
      });
    }
    if (form.idFrontUri) {
      fd.append("id_card", {
        uri: form.idFrontUri,
        name: form.idFrontName,
        type: "image/jpeg",
      });
    }
    if (form.idBackUri) {
      fd.append("id_card_back", {
        uri: form.idBackUri,
        name: form.idBackName,
        type: "image/jpeg",
      });
    }
    return fd;
  };

  const submit = async () => {
    if (!validate()) return;
    setLoading(true);
    try {
      await register(buildFormData());
      navigation.replace("Success", { regType });
    } catch (e) {
      Alert.alert("Registration", e.message || "Could not submit. Try again later.");
    } finally {
      setLoading(false);
    }
  };

  const renderStep0 = () => (
    <>
      <View style={styles.banner}>
        <Text style={styles.bannerTag}>{isLegacy ? "Legacy Alumni" : "New Alumni"} Registration</Text>
        <Text style={styles.bannerTitle}>
          {isLegacy ? "Alumni Card Verification" : "Student ID Verification"}
        </Text>
        <Text style={styles.bannerDesc}>
          {isLegacy
            ? "Upload both sides of your OLFU Alumni Card. Enter your 16-digit card number below."
            : "Upload both sides of your Student ID. Alumni ID is assigned after verification."}
        </Text>
      </View>
      <View style={styles.idRow}>
        <IdUploadCard
          label={`Front of ${isLegacy ? "Alumni Card" : "Student ID"} *`}
          icon="🪪"
          fileName={form.idFrontName}
          onSelect={() => handlePickId("front")}
        />
        <IdUploadCard
          label={`Back of ${isLegacy ? "Alumni Card" : "Student ID"} *`}
          icon="📄"
          fileName={form.idBackName}
          onSelect={() => handlePickId("back")}
        />
      </View>
      {errors.idFront ? <Text style={styles.fieldErr}>⚠ {errors.idFront}</Text> : null}
      {errors.idBack ? <Text style={styles.fieldErr}>⚠ {errors.idBack}</Text> : null}
      {isLegacy ? (
        <Input
          label="Existing Alumni ID Number *"
          value={form.alumni_id_number}
          onChangeText={(v) => set("alumni_id_number", v.replace(/\D/g, "").slice(0, 16))}
          placeholder="16-digit number on Alumni Card"
          hint="Numbers only, max 16 digits"
          error={errors.alumni_id_number}
          keyboardType="number-pad"
        />
      ) : (
        <View style={styles.pendingBox}>
          <Text style={styles.pendingLabel}>Alumni ID</Text>
          <Text style={styles.pendingVal}>[ Pending Approval ]</Text>
        </View>
      )}
      <TouchableOpacity style={styles.photoBox} onPress={handlePickPhoto}>
        <Text style={styles.photoLabel}>Profile Photo (2×2) *</Text>
        <Text style={styles.photoEmoji}>{form.profile_photo_name ? "😊" : "👤"}</Text>
        <Text style={styles.photoHint}>
          {form.profile_photo_name ? `✓ ${form.profile_photo_name}` : "Tap to upload 2×2 photo"}
        </Text>
      </TouchableOpacity>
      {errors.photo ? <Text style={styles.fieldErr}>⚠ {errors.photo}</Text> : null}
      <View style={styles.grid3}>
        <View style={{ flex: 1 }}>
          <Input label="Last Name *" value={form.lastname} onChangeText={(v) => set("lastname", v)} error={errors.lastname} />
        </View>
        <View style={{ flex: 1 }}>
          <Input label="First Name *" value={form.firstname} onChangeText={(v) => set("firstname", v)} error={errors.firstname} />
        </View>
        <View style={{ width: 56 }}>
          <Input label="M.I." value={form.middleInitial} onChangeText={(v) => set("middleInitial", v)} />
        </View>
      </View>
      <Input label="Campus" value={form.campus} readOnly hint="Antipolo City only" />
      <Select label="Year Graduated *" value={form.year_graduated} onValueChange={(v) => set("year_graduated", v)} options={years} error={errors.year_graduated} />
      <Select label="Month" value={form.month_graduated} onValueChange={(v) => set("month_graduated", v)} options={MONTHS} />
      <Select
        label="College *"
        value={form.college}
        onValueChange={(v) => setForm((f) => ({ ...f, college: v, degree: "" }))}
        options={[
          "College of Computer Studies",
          "College of Engineering",
          "College of Business & Accountancy",
          "College of Nursing",
          "College of Medicine",
        ].map((v) => ({ value: v, label: v }))}
        error={errors.college}
      />
      <Select label="Degree *" value={form.degree} onValueChange={(v) => set("degree", v)} options={PROGRAMS} error={errors.degree} />
    </>
  );

  const renderStep1 = () => (
    <>
      <Select label="Gender *" value={form.gender} onValueChange={(v) => set("gender", v)} options={["Male", "Female", "Prefer not to say"].map((v) => ({ value: v, label: v }))} error={errors.gender} />
      <Select label="Civil Status *" value={form.civil_status} onValueChange={(v) => set("civil_status", v)} options={["Single", "Married", "Widowed", "Separated"].map((v) => ({ value: v, label: v }))} error={errors.civil_status} />
      <Input label="Personal Email * (@gmail.com or @yahoo.com)" type="email" value={form.personal_email} onChangeText={(v) => set("personal_email", v)} placeholder="you@gmail.com" icon="📧" error={errors.personal_email} hint="This is also your login email" />
      <Input label="Contact Number *" value={form.personal_contact} onChangeText={(v) => set("personal_contact", v)} placeholder="09XXXXXXXXX" hint="10–11 digits" error={errors.personal_contact} keyboardType="phone-pad" />
      <Input label="Emergency Contact" value={form.emergency_contact} onChangeText={(v) => set("emergency_contact", v)} placeholder="Optional" keyboardType="phone-pad" />
      <Input label="Complete Address *" value={form.address} onChangeText={(v) => set("address", v)} placeholder="Street, Barangay, City" error={errors.address} />
    </>
  );

  const renderStep2 = () => (
    <>
      <Text style={styles.sectionQ}>Licensure Examination *</Text>
      <RadioGroup
        value={form.passed_licensure}
        onChange={(v) => set("passed_licensure", v)}
        error={errors.passed_licensure}
        options={[
          { value: "yes", label: "Yes, I passed" },
          { value: "no", label: "No, I didn't" },
          { value: "not_applicable", label: "Not applicable for my course" },
          { value: "not_yet", label: "Not yet — plan to take it soon" },
        ]}
      />
      <Text style={styles.sectionQ}>Enrolled in Post-Graduate studies? *</Text>
      <RadioGroup
        value={form.enrolled_post_grad}
        onChange={(v) => set("enrolled_post_grad", v)}
        error={errors.enrolled_post_grad}
        options={[
          { value: "yes", label: "Yes" },
          { value: "no", label: "No" },
          { value: "not_applicable", label: "Not applicable — still graduating" },
        ]}
      />
      <Input label="Licensure Exam Name" value={form.licensure_exam} onChangeText={(v) => set("licensure_exam", v)} placeholder="e.g. PRC Board Exam" />
      <Input label="Club / Org Involvement" value={form.club_involvement} onChangeText={(v) => set("club_involvement", v)} />
    </>
  );

  const renderStep3 = () => (
    <>
      <Select
        label="Employment Status *"
        value={form.employment_status}
        onValueChange={(v) => set("employment_status", v)}
        options={["Employed", "Self-employed", "Unemployed", "Student", "Prefer not to say"].map((v) => ({ value: v, label: v }))}
        error={errors.employment_status}
      />
      {(form.employment_status === "Employed" || form.employment_status === "Self-employed") && (
        <>
          <Input label="Company / Employer" value={form.company} onChangeText={(v) => set("company", v)} />
          <Input label="Position / Job Title" value={form.position} onChangeText={(v) => set("position", v)} />
        </>
      )}
      <Text style={styles.sectionQ}>College prepared me well for my career *</Text>
      <Likert value={form.college_prepared} onChange={(v) => set("college_prepared", v)} error={errors.college_prepared} />
      <Text style={styles.sectionQ}>I am proud to be an OLFU alumni *</Text>
      <Likert value={form.proud_alumni} onChange={(v) => set("proud_alumni", v)} error={errors.proud_alumni} />
    </>
  );

  const renderStep4 = () => (
    <>
      <View style={styles.info}>
        <Text style={styles.infoText}>
          ℹ {isLegacy ? "Your existing Alumni ID will be linked after verification." : "Your Alumni ID will be auto-assigned after Student ID verification."} You'll receive an email upon approval.
        </Text>
      </View>
      <Input label="Login Email" value={form.personal_email} readOnly icon="📧" hint="Synced from personal email" error={errors.account_email} />
      <Input label="Password *" type="password" value={form.password} onChangeText={(v) => set("password", v)} error={errors.password} icon="🔒" />
      <Input label="Confirm Password *" type="password" value={form.confirm_password} onChangeText={(v) => set("confirm_password", v)} error={errors.confirm_password} icon="🔒" />
      <View style={[styles.consentBox, errors.consent && styles.consentErr]}>
        <Switch value={form.consent} onValueChange={(v) => set("consent", v)} trackColor={{ true: T.forest }} />
        <Text style={styles.consentText}>I have read and agree to the Data Privacy Consent statement. *</Text>
      </View>
      {errors.consent ? <Text style={styles.fieldErr}>⚠ {errors.consent}</Text> : null}
    </>
  );

  const steps = [renderStep0, renderStep1, renderStep2, renderStep3, renderStep4];

  return (
    <View style={styles.flex}>
      <View style={styles.header}>
        <TouchableOpacity onPress={step === 0 ? () => navigation.goBack() : prev}>
          <Text style={styles.back}>‹</Text>
        </TouchableOpacity>
        <View style={styles.headerMid}>
          <Text style={styles.headerTitle}>{isLegacy ? "Legacy" : "New"} Registration</Text>
          <Text style={styles.headerSub}>
            Step {step + 1} of 5 — {STEP_LABELS[step]}
          </Text>
        </View>
        <View style={[styles.badge, isLegacy && styles.badgeLegacy]}>
          <Text style={styles.badgeText}>{isLegacy ? "LEGACY" : "NEW"}</Text>
        </View>
      </View>
      <StepIndicator current={step} labels={STEP_LABELS} />
      <ScrollView style={styles.scroll} contentContainerStyle={styles.scrollPad}>
        {steps[step]()}
      </ScrollView>
      <View style={styles.footer}>
        {step < 4 ? (
          <PrimaryBtn onPress={next}>Continue →</PrimaryBtn>
        ) : (
          <PrimaryBtn onPress={submit} disabled={!form.consent} loading={loading}>
            Submit Registration
          </PrimaryBtn>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: T.cream },
  header: {
    backgroundColor: T.forest,
    paddingVertical: 12,
    paddingHorizontal: 16,
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
  },
  back: { fontSize: 28, color: "rgba(255,255,255,0.7)" },
  headerMid: { flex: 1 },
  headerTitle: { fontSize: 17, fontWeight: "700", color: T.white },
  headerSub: { fontSize: 9, color: "rgba(255,255,255,0.55)" },
  badge: {
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 100,
    backgroundColor: "rgba(255,255,255,0.12)",
  },
  badgeLegacy: { backgroundColor: "rgba(184,146,42,0.25)" },
  badgeText: { fontSize: 8, fontWeight: "700", color: T.goldLt },
  scroll: { flex: 1 },
  scrollPad: { padding: 16, paddingBottom: 24 },
  footer: {
    padding: 16,
    backgroundColor: T.white,
    borderTopWidth: 1,
    borderTopColor: T.mist,
  },
  banner: {
    backgroundColor: T.pine,
    borderRadius: 14,
    padding: 16,
    marginBottom: 16,
  },
  bannerTag: { fontSize: 8, fontWeight: "700", color: T.gold, letterSpacing: 1.4, textTransform: "uppercase" },
  bannerTitle: { fontSize: 16, fontWeight: "700", color: T.white, marginVertical: 4 },
  bannerDesc: { fontSize: 10, color: "rgba(255,255,255,0.6)", lineHeight: 15 },
  idRow: { flexDirection: "row", gap: 10, marginBottom: 8 },
  pendingBox: {
    backgroundColor: T.snow,
    borderWidth: 1,
    borderColor: T.mist,
    borderRadius: 12,
    padding: 12,
    marginBottom: 14,
  },
  pendingLabel: { fontSize: 9, fontWeight: "700", color: T.silver, textTransform: "uppercase" },
  pendingVal: { fontSize: 18, fontWeight: "700", color: T.forest, marginTop: 4 },
  photoBox: {
    borderWidth: 1.5,
    borderStyle: "dashed",
    borderColor: T.fog,
    borderRadius: 12,
    padding: 14,
    alignItems: "center",
    marginBottom: 14,
  },
  photoLabel: { fontSize: 9, fontWeight: "700", color: T.silver, marginBottom: 8 },
  photoEmoji: { fontSize: 28, marginBottom: 6 },
  photoHint: { fontSize: 9, color: T.silver },
  grid3: { flexDirection: "row", gap: 8 },
  sectionQ: { fontSize: 11, fontWeight: "600", color: T.slate, marginBottom: 6 },
  likertWrap: { marginBottom: 14 },
  likertRow: { flexDirection: "row", flexWrap: "wrap", gap: 5 },
  chip: {
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 100,
    borderWidth: 1.5,
    borderColor: T.fog,
  },
  chipOn: { backgroundColor: T.forest, borderColor: T.forest },
  chipText: { fontSize: 9, fontWeight: "600", color: T.slate },
  chipTextOn: { color: T.white },
  radio: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    padding: 10,
    borderRadius: 12,
    borderWidth: 1.5,
    borderColor: T.fog,
    marginBottom: 6,
  },
  radioOn: { borderColor: T.leaf, backgroundColor: "rgba(27,94,53,0.06)" },
  radioDot: { width: 14, height: 14, borderRadius: 7, borderWidth: 2, borderColor: T.fog },
  radioDotOn: { borderColor: T.forest, backgroundColor: T.forest },
  radioLabel: { fontSize: 11, color: T.ink },
  radioLabelOn: { color: T.leaf, fontWeight: "600" },
  fieldErr: { fontSize: 9, color: T.danger, marginBottom: 8 },
  info: {
    backgroundColor: T.amberPale,
    borderRadius: 12,
    padding: 12,
    marginBottom: 14,
    borderWidth: 1,
    borderColor: "rgba(217,119,6,0.2)",
  },
  infoText: { fontSize: 10, color: "#92400e", lineHeight: 16 },
  consentBox: {
    flexDirection: "row",
    alignItems: "flex-start",
    gap: 10,
    padding: 12,
    borderWidth: 1.5,
    borderColor: T.fog,
    borderRadius: 12,
    marginTop: 8,
  },
  consentErr: { borderColor: T.danger },
  consentText: { flex: 1, fontSize: 10, color: T.ink, lineHeight: 16 },
});
