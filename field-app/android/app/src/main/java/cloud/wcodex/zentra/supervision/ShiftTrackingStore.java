package cloud.wcodex.zentra.supervision;

import android.content.Context;
import android.content.SharedPreferences;

final class ShiftTrackingStore {
    private static final String PREFS = "zentra_shift_tracking";
    private static final String API = "api_base";
    private static final String TOKEN = "token";
    private static final String USER = "user_id";

    private ShiftTrackingStore() {}

    static void save(Context context, String apiBase, String token, String userId) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit()
            .putString(API, apiBase)
            .putString(TOKEN, token)
            .putString(USER, userId)
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

    static String userId(Context context) {
        return context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).getString(USER, "");
    }

    static void clear(Context context) {
        context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().clear().apply();
    }
}
