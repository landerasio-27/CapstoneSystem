# Keep ML Kit barcode scanning + its reflection-based component/registrar graph.
# Without these, R8 can strip/obfuscate the barcode-scanner creator, causing a
# NullPointerException ("invoke ... on a null object reference") when the camera
# scanner starts. Applies only if minification is enabled.
-keep class com.google.mlkit.** { *; }
-keep interface com.google.mlkit.** { *; }
-keep class com.google.android.gms.internal.mlkit_vision_barcode.** { *; }
-keep class com.google.android.gms.internal.mlkit_vision_common.** { *; }
-keep class com.google.android.odml.** { *; }

# ML Kit registrars are referenced only from the manifest via reflection.
-keep class * implements com.google.firebase.components.ComponentRegistrar { *; }
-keep class * extends com.google.mlkit.common.sdkinternal.Registrar { *; }

-dontwarn com.google.mlkit.**
-dontwarn com.google.android.gms.**

# mobile_scanner plugin
-keep class dev.steenbakker.mobile_scanner.** { *; }

# CameraX
-keep class androidx.camera.** { *; }
-dontwarn androidx.camera.**
