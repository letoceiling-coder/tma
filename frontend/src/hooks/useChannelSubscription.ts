import { useState, useEffect, useCallback } from "react";

interface ChannelInfo {
  username: string;
  external_url?: string | null;
  id?: number;
}

interface UseChannelSubscriptionOptions {
  channelUsernames: string[]; // Массив каналов, например: ["bunny_world_2025", "another_channel"]
  channels?: ChannelInfo[]; // Полная информация о каналах (приоритет над channelUsernames)
  onSubscriptionConfirmed?: () => void;
}

interface ChannelStatus {
  username: string;
  external_url?: string | null;
  id?: number;
  isSubscribed: boolean | null;
}

interface ChannelSubscriptionResult {
  isChecking: boolean;
  channels: ChannelStatus[];
  allSubscribed: boolean; // Все ли каналы подписаны
  checkSubscriptions: (force?: boolean) => void;
  openChannel: (username: string, channelId?: number, externalUrl?: string | null) => void;
  copyChannelLink: (username: string, externalUrl?: string | null) => Promise<boolean>;
}

export const useChannelSubscription = ({
  channelUsernames,
  channels: channelsInfo,
  onSubscriptionConfirmed,
}: UseChannelSubscriptionOptions): ChannelSubscriptionResult => {
  const [isChecking, setIsChecking] = useState(false);
  
  // Используем channelsInfo если предоставлено, иначе создаем из channelUsernames
  const initialChannels: ChannelStatus[] = channelsInfo
    ? channelsInfo.map((ch) => ({
        username: ch.username,
        external_url: ch.external_url,
        id: ch.id,
        isSubscribed: null,
      }))
    : channelUsernames.map((username) => ({
        username,
        isSubscribed: null,
      }));
  
  const [channels, setChannels] = useState<ChannelStatus[]>(initialChannels);

  const checkSubscriptions = useCallback(async (force: boolean = false) => {
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
      
      const usernamesToCheck = channelsInfo 
        ? channelsInfo.map(ch => ch.username)
        : channelUsernames;
      
      const checkPromises = usernamesToCheck.map(async (username) => {
          // УБРАНО: автоматическое разрешение через localStorage для production
          // Проверяем только через API

          // Вызываем backend API для проверки подписки
          try {
            const apiUrl = import.meta.env.VITE_API_URL || '';
            // Добавляем параметр force для принудительной проверки (инвалидация кеша)
            const forceParam = force ? '?force=1' : '';
            const apiPath = apiUrl ? `${apiUrl}/api/check-subscription/${username}${forceParam}` 
                                   : `/api/check-subscription/${username}${forceParam}`;
            
            console.log(`Проверка подписки на @${username} через API: ${apiPath}${force ? ' (force)' : ''}`);
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
      
      // Объединяем результаты проверки с информацией о каналах
      const mergedResults: ChannelStatus[] = results.map((result) => {
        const channelInfo = channelsInfo?.find(ch => ch.username === result.username);
        return {
          ...result,
          external_url: channelInfo?.external_url,
          id: channelInfo?.id,
        };
      });
      
      setChannels(mergedResults);

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
  }, [channelUsernames, channelsInfo, onSubscriptionConfirmed]);

  const copyChannelLink = useCallback(async (username: string, externalUrl?: string | null): Promise<boolean> => {
    // Используем external_url если указано, иначе стандартную ссылку
    const channelUrl = externalUrl || `https://t.me/${username}`;
    
    try {
      // Пробуем использовать Clipboard API
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(channelUrl);
        return true;
      } else {
        // Fallback для старых браузеров
        const textArea = document.createElement('textarea');
        textArea.value = channelUrl;
        textArea.style.position = 'fixed';
        textArea.style.opacity = '0';
        document.body.appendChild(textArea);
        textArea.select();
        try {
          document.execCommand('copy');
          document.body.removeChild(textArea);
          return true;
        } catch (e) {
          document.body.removeChild(textArea);
          return false;
        }
      }
    } catch (error) {
      console.error('Error copying link:', error);
      return false;
    }
  }, []);

  const openChannel = useCallback(async (username: string, channelId?: number, externalUrl?: string | null) => {
    const tg = window.Telegram?.WebApp;
    
    // Используем external_url если указано, иначе стандартную ссылку
    const channelUrl = externalUrl || `https://t.me/${username}`;
    const platform = tg?.platform || 'web';
    
    // Логируем клик перед открытием
    if (channelId) {
      try {
        const apiUrl = import.meta.env.VITE_API_URL || '';
        const apiPath = apiUrl ? `${apiUrl}/api/channels/${channelId}/click` : `/api/channels/${channelId}/click`;
        
        // Извлекаем UTM-параметры из externalUrl если есть
        let utmParams = null;
        if (externalUrl) {
          try {
            const url = new URL(externalUrl);
            const params: Record<string, string> = {};
            url.searchParams.forEach((value, key) => {
              if (key.startsWith('utm_')) {
                params[key] = value;
              }
            });
            if (Object.keys(params).length > 0) {
              utmParams = params;
            }
          } catch (e) {
            // Игнорируем ошибки парсинга URL
          }
        }
        
        await fetch(apiPath, {
          method: 'POST',
          headers: {
            'X-Telegram-Init-Data': tg?.initData || '',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            utm_params: utmParams,
          }),
        }).catch((err) => {
          console.warn('Failed to log channel click:', err);
        });
      } catch (error) {
        console.warn('Error logging channel click:', error);
      }
    }
    
    // Определяем, является ли это десктопной версией
    const isDesktop = platform === 'tdesktop' || platform === 'web' || platform === 'macos' || platform === 'windows' || platform === 'linux';
    
    let opened = false;
    
    try {
      if (tg) {
        // Для мобильных платформ используем openTelegramLink
        if (!isDesktop && tg.openTelegramLink) {
          try {
            tg.openTelegramLink(channelUrl);
            opened = true;
          } catch (e) {
            console.warn('openTelegramLink failed on mobile, trying openLink:', e);
            if (tg.openLink) {
              tg.openLink(channelUrl);
              opened = true;
            }
          }
        } else {
          // Для десктопа используем openLink (более надежно, чем openTelegramLink)
          if (tg.openLink) {
            try {
              tg.openLink(channelUrl);
              opened = true;
            } catch (e) {
              console.warn('openLink failed, trying window.open:', e);
            }
          } else if (tg.openTelegramLink) {
            // Если openLink недоступен, пробуем openTelegramLink
            try {
              tg.openTelegramLink(channelUrl);
              opened = true;
            } catch (e) {
              console.warn('openTelegramLink failed on desktop:', e);
            }
          }
        }
      }
      
      // Если ничего не сработало, используем window.open как последний fallback
      if (!opened) {
        window.open(channelUrl, "_blank");
        opened = true;
      }
    } catch (error) {
      console.error('Error opening channel:', error);
      // Fallback на обычное открытие
      if (!opened) {
        window.open(channelUrl, "_blank");
      }
    }

    // После открытия канала, проверяем подписку с задержкой
    // Даем время Telegram API обновиться после подписки
    const delay = isDesktop ? 3500 : 2500; // 2.5-3.5 секунды для обновления API
    setTimeout(() => {
      checkSubscriptions(true); // Принудительная проверка (инвалидация кеша)
    }, delay);
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
    copyChannelLink,
  };
};
