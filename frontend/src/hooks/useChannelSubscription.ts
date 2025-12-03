import { useState, useEffect, useCallback } from "react";

interface UseChannelSubscriptionOptions {
  channelUsernames: string[]; // Массив каналов, например: ["bunny_world_2025", "another_channel"]
  onSubscriptionConfirmed?: () => void;
}

interface ChannelStatus {
  username: string;
  isSubscribed: boolean | null;
}

interface ChannelSubscriptionResult {
  isChecking: boolean;
  channels: ChannelStatus[];
  allSubscribed: boolean; // Все ли каналы подписаны
  checkSubscriptions: () => void;
  openChannel: (username: string) => void;
}

export const useChannelSubscription = ({
  channelUsernames,
  onSubscriptionConfirmed,
}: UseChannelSubscriptionOptions): ChannelSubscriptionResult => {
  const [isChecking, setIsChecking] = useState(false);
  const [channels, setChannels] = useState<ChannelStatus[]>(
    channelUsernames.map((username) => ({
      username,
      isSubscribed: null,
    }))
  );

  const checkSubscriptions = useCallback(async () => {
    const tg = window.Telegram?.WebApp;
    
    if (!tg) {
      // Если не в Telegram, разрешаем доступ для разработки
      setChannels(
        channelUsernames.map((username) => ({
          username,
          isSubscribed: true,
        }))
      );
      onSubscriptionConfirmed?.();
      return;
    }

    setIsChecking(true);

    try {
      // Проверяем версию Telegram WebApp API
      const version = parseFloat(tg.version || "0");
      
      // Для версий 7.0+ используем новый API
      if (version >= 7.0 && (tg as any).checkChatSubscription) {
        const checkPromises = channelUsernames.map(async (username) => {
          try {
            const result = await (tg as any).checkChatSubscription({
              chat_id: `@${username}`,
            });
            
            return {
              username,
              isSubscribed: result?.is_subscribed || false,
            };
          } catch (error) {
            console.error(`Error checking subscription for @${username}:`, error);
            return {
              username,
              isSubscribed: false,
            };
          }
        });

        const results = await Promise.all(checkPromises);
        setChannels(results);

        // Если все каналы подписаны, вызываем callback
        const allSubscribed = results.every((r) => r.isSubscribed);
        if (allSubscribed) {
          onSubscriptionConfirmed?.();
        }
      } else {
        // Для старых версий или если метод недоступен
        // Используем метод через проверку через backend
        const checkPromises = channelUsernames.map(async (username) => {
          // Для тестирования можно установить флаг в localStorage
          const testSubscribed = localStorage.getItem(`channel_subscribed_${username}`);
          
          if (testSubscribed === "true") {
            return {
              username,
              isSubscribed: true,
            };
          }

          // Вызываем backend API для проверки подписки
          try {
            const apiUrl = import.meta.env.VITE_API_URL || '';
            const apiPath = apiUrl ? `${apiUrl}/api/check-subscription/${username}` 
                                   : `/api/check-subscription/${username}`;
            
            const response = await fetch(apiPath, {
              method: "GET",
              headers: {
                "X-Telegram-Init-Data": tg.initData || "",
                "Content-Type": "application/json",
                "Accept": "application/json",
              },
            });
            
            if (response.ok) {
              const data = await response.json();
              return {
                username,
                isSubscribed: data.is_subscribed || false,
              };
            } else {
              // Если API недоступен, разрешаем доступ (для разработки)
              return {
                username,
                isSubscribed: true,
              };
            }
          } catch (error) {
            console.error(`Error checking subscription via API for @${username}:`, error);
            // Для разработки разрешаем доступ
            return {
              username,
              isSubscribed: true,
            };
          }
        });

        const results = await Promise.all(checkPromises);
        setChannels(results);

        // Если все каналы подписаны, вызываем callback
        const allSubscribed = results.every((r) => r.isSubscribed);
        if (allSubscribed) {
          onSubscriptionConfirmed?.();
        }
      }
    } catch (error) {
      console.error("Error in checkSubscriptions:", error);
      setChannels(
        channelUsernames.map((username) => ({
          username,
          isSubscribed: false,
        }))
      );
    } finally {
      setIsChecking(false);
    }
  }, [channelUsernames, onSubscriptionConfirmed]);

  const openChannel = useCallback((username: string) => {
    const tg = window.Telegram?.WebApp;
    const channelUrl = `https://t.me/${username}`;
    
    if (tg?.openTelegramLink) {
      tg.openTelegramLink(channelUrl);
    } else {
      window.open(channelUrl, "_blank");
    }

    // После открытия канала, через некоторое время проверяем подписку снова
    setTimeout(() => {
      checkSubscriptions();
    }, 3000);
  }, [checkSubscriptions]);

  // Автоматическая проверка при монтировании
  useEffect(() => {
    checkSubscriptions();
  }, [checkSubscriptions]);

  const allSubscribed = channels.every((ch) => ch.isSubscribed === true);

  return {
    isChecking,
    channels,
    allSubscribed,
    checkSubscriptions,
    openChannel,
  };
};
