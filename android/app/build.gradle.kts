plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

// Sürüm: app/surum.txt (ör. 1.0.0); derleme numarası CI'da GITHUB_RUN_NUMBER.
val surum = file("surum.txt").readText().trim()
val derleme = (System.getenv("GITHUB_RUN_NUMBER") ?: "1").toInt()

android {
    namespace = "tr.com.optiflow.asistan"
    compileSdk = 34

    defaultConfig {
        applicationId = "tr.com.optiflow.asistan"
        minSdk = 31
        targetSdk = 34
        versionCode = derleme
        versionName = surum
    }

    signingConfigs {
        create("yukleme") {
            // Elden kurulum (sideload) imzası. Anahtar deposu CI'da ASISTAN_KEYSTORE_B64 sırrından açılır;
            // yoksa depodaki android/keystore/README.md'deki gibi yerelde üretilir. Git'e girmez.
            storeFile = file(System.getenv("ASISTAN_KEYSTORE") ?: "../keystore/asistan.jks")
            storePassword = System.getenv("ASISTAN_KEYSTORE_SIFRE") ?: "optiflow-asistan"
            keyAlias = "asistan"
            keyPassword = System.getenv("ASISTAN_KEYSTORE_SIFRE") ?: "optiflow-asistan"
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            signingConfig = signingConfigs.getByName("yukleme")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions {
        jvmTarget = "17"
    }
}
