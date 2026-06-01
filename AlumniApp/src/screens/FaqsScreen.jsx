import PortalInfoScreen from "./PortalInfoScreen";
import { FAQ_SECTIONS } from "../constants/portalContent";

export default function FaqsScreen(props) {
  return (
    <PortalInfoScreen
      {...props}
      route={{
        ...props.route,
        params: {
          title: "FAQs",
          faqSections: FAQ_SECTIONS,
        },
      }}
    />
  );
}
