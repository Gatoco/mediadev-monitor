#!/usr/bin/env bash
# Builds SemaOPS Control (WebView APK) without Gradle: aapt2 + javac + d8 + apksigner.
#   android/build.sh <host[:port]> <demo-token>
# Output: laravel/public/semaops-control.apk (served by the monitor at /semaops-control.apk).
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST="${1:?host[:port], e.g. 100.77.155.21:8080}"
TOKEN="${2:?demo token}"
SDK="${ANDROID_HOME:-$HOME/Android/Sdk}"
BT="$(ls -d "$SDK"/build-tools/* | sort -V | tail -1)"
JAR="$(ls -d "$SDK"/platforms/android-* | sort -V | tail -1)/android.jar"
OUT="$HERE/build"
APK="$HERE/../laravel/public/semaops-control.apk"
KS="$HERE/debug.keystore"

rm -rf "$OUT" && mkdir -p "$OUT/res/values" "$OUT/gen" "$OUT/classes"
cp -r "$HERE/res/." "$OUT/res/"
cat > "$OUT/res/values/config.xml" <<EOF
<?xml version="1.0" encoding="utf-8"?>
<resources><string name="default_url" translatable="false">http://${HOST}/demo?k=${TOKEN}</string></resources>
EOF

"$BT/aapt2" compile --dir "$OUT/res" -o "$OUT/res.zip"
"$BT/aapt2" link -o "$OUT/base.apk" -I "$JAR" --manifest "$HERE/AndroidManifest.xml" \
  --min-sdk-version 26 --target-sdk-version 34 --java "$OUT/gen" "$OUT/res.zip"
javac -nowarn -encoding UTF-8 --release 8 -Xlint:-options -classpath "$JAR" -d "$OUT/classes" \
  "$OUT"/gen/cl/semaops/control/R.java "$HERE"/src/cl/semaops/control/*.java
"$BT/d8" --release --min-api 26 --lib "$JAR" --output "$OUT" $(find "$OUT/classes" -name '*.class')
(cd "$OUT" && zip -q base.apk classes.dex)
"$BT/zipalign" -f 4 "$OUT/base.apk" "$OUT/aligned.apk"

[ -f "$KS" ] || keytool -genkeypair -keystore "$KS" -storepass android -keypass android -alias demo \
  -keyalg RSA -keysize 2048 -validity 10000 -dname "CN=SemaOPS Demo" >/dev/null 2>&1
"$BT/apksigner" sign --ks "$KS" --ks-pass pass:android --out "$APK" "$OUT/aligned.apk"
"$BT/apksigner" verify "$APK"
echo "APK listo: $APK"
