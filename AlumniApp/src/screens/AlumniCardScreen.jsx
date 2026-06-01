import PortalInfoScreen from "./PortalInfoScreen";
import { ALUMNI_CARD_CONTENT } from "../constants/portalContent";

export default function AlumniCardScreen(props) {
  return (
    <PortalInfoScreen
      {...props}
      route={{
        ...props.route,
        params: {
          title: "Alumni Card",
          hero: {
            title: ALUMNI_CARD_CONTENT.title,
            subtitle: ALUMNI_CARD_CONTENT.subtitle,
          },
          sections: [
            { title: "Benefits", list: ALUMNI_CARD_CONTENT.benefits },
            { title: "How to get your card", list: ALUMNI_CARD_CONTENT.steps },
            { note: ALUMNI_CARD_CONTENT.note },
          ],
        },
      }}
    />
  );
}
