# Add project specific ProGuard rules here.
-keepattributes *Annotation*
-keepclassmembers class * {
    @androidx.room.* <methods>;
}
-keep class uz.payday.app.data.remote.dto.** { *; }
