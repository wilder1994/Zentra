package cloud.wcodex.zentra.supervision;

import android.content.Context;
import android.content.SharedPreferences;

final class ShiftTrackingStore {
    private static final String PREFS = "zentra_shift_tracking";
    private static final String API = "api_base";
    private static final String TOKEN = "token";

    private ShiftTrackingStore() {}

    static void save(Context context, String apiBase, String token) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit()
            .putString(API, apiBase)
            .putString(TOKEN, token)
            .apply();
    }

    static void saveToken(Context context, String token) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit()
            .putString(TOKEN, token)
            .apply();
    }

    static String apiBase(Context context) {
        return context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(API, "");
    }

    static String token(Context context) {
        return context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(TOKEN, "");
    }

    static void clear(Context context) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().clear().apply();
    }
}
