import { useEffect, useState } from "react";

import {

  View,

  Text,

  TouchableOpacity,

  StyleSheet,

  StatusBar,

  ActivityIndicator,

} from "react-native";

import { NavigationContainer } from "@react-navigation/native";

import { createNativeStackNavigator } from "@react-navigation/native-stack";

import { createBottomTabNavigator } from "@react-navigation/bottom-tabs";

import { AuthContext } from "../context/AuthContext";

import { DrawerProvider, useDrawer } from "../context/DrawerContext";

import { T } from "../constants/colors";

import { getStoredUser, getStoredToken, persistUser } from "../api/auth";

import { fetchFullProfile } from "../api/alumni";

import BottomNav from "../components/BottomNav";

import AppDrawer from "../components/AppDrawer";



import SplashScreen from "../screens/SplashScreen";

import LoginScreen from "../screens/LoginScreen";

import RegTypeScreen from "../screens/RegTypeScreen";

import RegisterScreen from "../screens/RegisterScreen";

import SuccessScreen from "../screens/SuccessScreen";

import DashboardScreen from "../screens/DashboardScreen";

import DirectoryScreen from "../screens/DirectoryScreen";

import EventsScreen from "../screens/EventsScreen";

import MessagesScreen from "../screens/MessagesScreen";

import ProfileScreen from "../screens/ProfileScreen";

import EditProfileScreen from "../screens/EditProfileScreen";

import AlumniDetailScreen from "../screens/AlumniDetailScreen";

import CareerScreen from "../screens/CareerScreen";

import MyCareerScreen from "../screens/MyCareerScreen";

import AboutScreen from "../screens/AboutScreen";

import FaqsScreen from "../screens/FaqsScreen";

import AlumniCardScreen from "../screens/AlumniCardScreen";
import EventDetailScreen from "../screens/EventDetailScreen";
import JobDetailScreen from "../screens/JobDetailScreen";



const Stack = createNativeStackNavigator();

const Tab = createBottomTabNavigator();



function AppHeader({ title }) {

  const { openDrawer } = useDrawer();



  return (

    <View style={hStyles.header}>

      <TouchableOpacity onPress={openDrawer} style={hStyles.btn} hitSlop={8}>

        <View style={hStyles.ham}>

          <View style={hStyles.hamLine} />

          <View style={hStyles.hamLine} />

          <View style={hStyles.hamLine} />

        </View>

      </TouchableOpacity>

      <Text style={hStyles.title}>{title}</Text>

      <View style={hStyles.btn} />

    </View>

  );

}



function TabShell({ title, children }) {

  return (

    <View style={{ flex: 1 }}>

      <AppHeader title={title} />

      {children}

    </View>

  );

}



const hStyles = StyleSheet.create({

  header: {

    height: 56,

    backgroundColor: T.forest,

    flexDirection: "row",

    alignItems: "center",

    justifyContent: "space-between",

    paddingHorizontal: 14,

    elevation: 4,

    shadowColor: "#000",

    shadowOpacity: 0.15,

    shadowOffset: { width: 0, height: 2 },

    shadowRadius: 4,

  },

  btn: { padding: 6, justifyContent: "center", minWidth: 36 },

  title: {

    fontSize: 19,

    fontWeight: "600",

    color: T.white,

    flex: 1,

    textAlign: "center",

  },

  ham: { gap: 5, justifyContent: "center" },

  hamLine: {

    width: 22,

    height: 2.5,

    backgroundColor: T.white,

    borderRadius: 2,

  },

});



function MainTabs() {

  return (

    <Tab.Navigator

      tabBar={(props) => <BottomNav {...props} />}

      screenOptions={{ headerShown: false }}

    >

      <Tab.Screen name="Dashboard">

        {() => (

          <TabShell title="Dashboard">

            <DashboardScreen />

          </TabShell>

        )}

      </Tab.Screen>

      <Tab.Screen name="Directory">

        {({ navigation }) => (

          <TabShell title="Directory">

            <DirectoryScreen navigation={navigation} />

          </TabShell>

        )}

      </Tab.Screen>

      <Tab.Screen name="Events">

        {({ navigation }) => (

          <TabShell title="Events">

            <EventsScreen navigation={navigation} />

          </TabShell>

        )}

      </Tab.Screen>

      <Tab.Screen name="Messages">

        {() => (

          <TabShell title="Messages">

            <MessagesScreen />

          </TabShell>

        )}

      </Tab.Screen>

    </Tab.Navigator>

  );

}



function MainWithDrawer({ navigation }) {

  return (

    <AppDrawer navigation={navigation}>

      <MainTabs />

    </AppDrawer>

  );

}



function AppStack() {

  const [user, setUser] = useState(null);

  const [token, setToken] = useState(null);

  const [booting, setBooting] = useState(true);



  useEffect(() => {

    Promise.all([getStoredUser(), getStoredToken()])

      .then(([u, t]) => {

        if (u) setUser(u);

        if (t) setToken(t);

      })

      .finally(() => setBooting(false));

  }, []);



  if (booting) {

    return (

      <View style={styles.boot}>

        <ActivityIndicator color={T.goldLt} size="large" />

      </View>

    );

  }



  const initialRoute = user ? "MainTabs" : "Splash";



  const refreshUser = async () => {

    try {

      const full = await fetchFullProfile();

      setUser(full);

      await persistUser(full);

    } catch {

      /* keep cached user */

    }

  };



  return (

    <AuthContext.Provider value={{ user, token, setUser, setToken, refreshUser }}>

      <NavigationContainer>

        <StatusBar barStyle="light-content" backgroundColor={T.forest} />

        <Stack.Navigator

          initialRouteName={initialRoute}

          screenOptions={{

            headerShown: false,

            contentStyle: { backgroundColor: T.cream },

          }}

        >

          <Stack.Screen name="Splash" component={SplashScreen} />

          <Stack.Screen name="Login" component={LoginScreen} />

          <Stack.Screen name="RegType" component={RegTypeScreen} />

          <Stack.Screen name="Register" component={RegisterScreen} />

          <Stack.Screen name="Success" component={SuccessScreen} />

          <Stack.Screen name="ProfileView" component={ProfileScreen} />

          <Stack.Screen name="EditProfile" component={EditProfileScreen} />

          <Stack.Screen name="AlumniDetail" component={AlumniDetailScreen} />

          <Stack.Screen name="Career" component={CareerScreen} />

          <Stack.Screen name="MyCareer" component={MyCareerScreen} />

          <Stack.Screen name="About" component={AboutScreen} />

          <Stack.Screen name="Faqs" component={FaqsScreen} />

          <Stack.Screen name="AlumniCard" component={AlumniCardScreen} />

          <Stack.Screen name="EventDetail" component={EventDetailScreen} />

          <Stack.Screen name="JobDetail" component={JobDetailScreen} />

          <Stack.Screen name="MainTabs" component={MainWithDrawer} />

        </Stack.Navigator>

      </NavigationContainer>

    </AuthContext.Provider>

  );

}



export default function AppNavigator() {

  return (

    <DrawerProvider>

      <AppStack />

    </DrawerProvider>

  );

}



const styles = StyleSheet.create({

  boot: {

    flex: 1,

    backgroundColor: T.forest,

    alignItems: "center",

    justifyContent: "center",

  },

});

