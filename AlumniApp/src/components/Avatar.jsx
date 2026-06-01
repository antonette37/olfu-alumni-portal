import { useState, useEffect } from "react";

import { View, Text, StyleSheet } from "react-native";

import { Image } from "expo-image";

import { T } from "../constants/colors";

import { resolveProfileImageUrl } from "../utils/profileImage";



export default function Avatar({

  initials,

  color = [T.leaf, T.moss],

  size = 44,

  style,

  uri,

  photo,

  userId,

  profileImageData,

}) {

  const [failed, setFailed] = useState(false);

  const imageUri = resolveProfileImageUrl(uri || photo, userId, profileImageData);



  useEffect(() => {

    setFailed(false);

  }, [imageUri]);



  const showPhoto = imageUri && !failed;



  return (

    <View

      style={[

        styles.wrap,

        {

          width: size,

          height: size,

          borderRadius: size / 2,

          backgroundColor: color[0],

          overflow: "hidden",

        },

        style,

      ]}

    >

      {showPhoto ? (

        <Image

          source={{ uri: imageUri }}

          style={{ width: size, height: size, borderRadius: size / 2 }}

          contentFit="cover"

          cachePolicy={imageUri.startsWith("data:") ? "memory" : "disk"}

          recyclingKey={String(userId || imageUri.slice(0, 48))}

          onError={() => setFailed(true)}

        />

      ) : (

        <Text style={[styles.text, { fontSize: size * 0.31 }]}>{initials}</Text>

      )}

    </View>

  );

}



const styles = StyleSheet.create({

  wrap: {

    alignItems: "center",

    justifyContent: "center",

    flexShrink: 0,

  },

  text: {

    color: T.white,

    fontWeight: "700",

  },

});


