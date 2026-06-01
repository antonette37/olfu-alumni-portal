import PortalInfoScreen from "./PortalInfoScreen";
import { ABOUT_CONTENT } from "../constants/portalContent";

export default function AboutScreen(props) {
  return (
    <PortalInfoScreen
      {...props}
      route={{
        ...props.route,
        params: {
          title: "About",
          hero: {
            kicker: "Our Lady of Fatima University",
            title: ABOUT_CONTENT.title,
            subtitle: ABOUT_CONTENT.intro,
            stats: [
              { value: "1967", label: "Year Founded" },
              { value: "8+", label: "Campuses" },
              { value: "Free", label: "First Alumni Card" },
              { value: "CCS", label: "College of Computer Studies" },
            ],
          },
          sections: [
            { title: "Mission", body: ABOUT_CONTENT.mission },
            {
              title: "Portal Features",
              items: ABOUT_CONTENT.features,
            },
            {
              title: "About OLFU and CCS",
              body: "OLFU builds professionals grounded in Veritas et Misericordia. CCS develops globally competitive technology professionals and innovators.",
            },
          ],
        },
      }}
    />
  );
}
