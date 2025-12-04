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
      // Если не в Telegram, блокируем доступ (для production)
      console.warn("Telegram WebApp не обнаружен - проверка подписки невозможна, доступ заблокирован");
      setChannels(
        channelUsernames.map((username) => ({
          username,
          isSubscribed: false,
        }))
      );
      return;
    }

    setIsChecking(true);

    try {
      // Всегда используем backend API для проверки подписки
      // Backend API более надежен, так как мы можем контролировать его логику
      // и убедиться, что бот является администратором каналов
      
      const checkPromises = channelUsernames.map(async (username) => {
          // УБРАНО: автоматическое разрешение через localStorage для production
          // Проверяем только через API

          // Вызываем backend API для проверки подписки
          try {
            const apiUrl = import.meta.env.VITE_API_URL || '';
            const apiPath = apiUrl ? `${apiUrl}/api/check-subscription/${username}` 
                                   : `/api/check-subscription/${username}`;
            
            console.log(`Проверка подписки на @${username} через API: ${apiPath}`);
            console.log(`InitData присутствует: ${!!tg.initData}`);
            
            const response = await fetch(apiPath, {
              method: "GET",
              headers: {
                "X-Telegram-Init-Data": tg.initData || "",
                "Content-Type": "application/json",
                "Accept": "application/json",
              },
            });
            
            console.log(`Ответ API для @${username}:`, response.status, response.statusText);
            
            const responseText = await response.text();
            console.log(`Текст ответа API для @${username}:`, responseText);
            
            let data;
            try {
              data = JSON.parse(responseText);
            } catch (e) {
              console.error(`Ошибка парсинга JSON для @${username}:`, e, responseText);
              return {
                username,
                isSubscribed: false,
              };
            }
            
            console.log(`Данные ответа API для @${username}:`, data);
            
            if (response.ok) {
              // Строгая проверка - только если явно true
              const isSubscribed = data.is_subscribed === true;
              console.log(`Результат проверки @${username}: isSubscribed=${isSubscribed}, status=${data.status}`);
              
              // Если в ответе есть debug информация, логируем её
              if (data.debug) {
                console.log(`Debug информация для @${username}:`, data.debug);
              }
              
              return {
                username,
                isSubscribed,
              };
            } else {
              // Если API недоступен или вернул ошибку, логируем и блокируем доступ
              console.warn(`API ошибка для @${username}:`, data.message || 'Unknown error', data);
              
              return {
                username,
                isSubscribed: false,
              };
            }
          } catch (error) {
            // При ошибке считаем что не подписан (блокируем доступ)
            console.error(`Исключение при проверке @${username}:`, error);
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
