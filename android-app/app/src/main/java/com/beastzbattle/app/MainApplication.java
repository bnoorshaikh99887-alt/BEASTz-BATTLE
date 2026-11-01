package com.beastzbattle.app;

import android.app.Application;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.os.Build;
import com.onesignal.OneSignal;

public class MainApplication extends Application {
    private static final String ONESIGNAL_APP_ID = "561bd59e-8355-47c8-88cc-0766688a105a";
    public static final String CHANNEL_ID = "beastz_push_alerts";

    @Override
    public void onCreate() {
        super.onCreate();

        // 1. Initialize OneSignal in Application class so notifications work even when app is KILLED / CLOSED
        try {
            OneSignal.setLogLevel(OneSignal.LOG_LEVEL.VERBOSE, OneSignal.LOG_LEVEL.NONE);
            OneSignal.initWithContext(this);
            OneSignal.setAppId(ONESIGNAL_APP_ID);

            // Display notification in status bar even when app is in foreground
            OneSignal.setNotificationWillShowInForegroundHandler(notificationReceivedEvent -> {
                notificationReceivedEvent.complete(notificationReceivedEvent.getNotification());
            });

            // Prompt permission for Android 13+
            OneSignal.promptForPushNotifications();
        } catch (Exception e) {
            e.printStackTrace();
        }

        // 2. Create notification channel on launch
        createNotificationChannel();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID,
                    "BEASTz BATTLE Announcements",
                    NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription("Live tournament alerts, room passwords & updates");
            channel.enableVibration(true);
            channel.enableLights(true);
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }
}
