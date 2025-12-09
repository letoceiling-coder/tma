import { useEffect, useState } from "react";
import { useChannelSubscription } from "@/hooks/useChannelSubscription";
import { haptic } from "@/lib/haptic";
import { ExternalLink, Copy, Check } from "lucide-react";

interface ChannelSubscriptionCheckProps {
  channelUsernames: string[]; // Массив каналов
  onSubscribed: () => void;
}

const ChannelSubscriptionCheck = ({
  channelUsernames,
  onSubscribed,
}: ChannelSubscriptionCheckProps) => {
  const { isChecking, channels, allSubscribed, checkSubscriptions, openChannel, copyChannelLink } =
    useChannelSubscription({
      channelUsernames,
      onSubscriptionConfirmed: onSubscribed,
    });
  
  const [copiedChannel, setCopiedChannel] = useState<string | null>(null);

  // Автоматически вызываем onSubscribed когда все каналы подписаны
  useEffect(() => {
    if (allSubscribed) {
      onSubscribed();
    }
  }, [allSubscribed, onSubscribed]);

  // Если проверка еще идет
  if (isChecking) {
    return (
      <div
        className="fixed inset-0 z-50 flex items-center justify-center"
        style={{
          background: "#FECFB2",
          fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
        }}
      >
        <div className="text-center">
          <p
            style={{
              fontSize: "16px",
              fontWeight: 600,
              color: "#CC5C47",
              margin: 0,
            }}
          >
            Проверка подписки...
          </p>
        </div>
      </div>
    );
  }

  // Если все каналы подписаны, не показываем ничего (будет показано основное приложение)
  if (allSubscribed) {
    return null;
  }

  // Получаем список неподписанных каналов (включая те, что еще не проверены - null)
  const unsubscribedChannels = channels.filter((ch) => ch.isSubscribed !== true);
  
  // Если все каналы еще не проверены (null), показываем их все
  const allUnchecked = channels.every((ch) => ch.isSubscribed === null);
  const channelsToShow = allUnchecked ? channels : unsubscribedChannels;

  // Показываем экран подписки
  return (
    <div
      className="fixed inset-0 z-50 flex flex-col items-center justify-center px-6 py-8"
      style={{
        background: "#FECFB2",
        fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
        overflowY: "auto",
      }}
    >
      {/* Заголовок */}
      <h1
        style={{
          fontSize: "24px",
          fontWeight: 700,
          color: "#CC5C47",
          textAlign: "center",
          marginBottom: "32px",
          marginTop: 0,
          lineHeight: "1.2",
        }}
      >
        Для участия подпишись на каналы
      </h1>

      {/* Список каналов */}
      <div
        style={{
          width: "100%",
          maxWidth: "400px",
          display: "flex",
          flexDirection: "column",
          gap: "16px",
          marginBottom: "32px",
        }}
      >
        {channelsToShow.map((channel) => (
          <div
            key={channel.username}
            style={{
              backgroundColor: "#FFFFFF",
              borderRadius: "16px",
              padding: "20px",
              display: "flex",
              flexDirection: "column",
              alignItems: "center",
              gap: "16px",
              boxShadow: "0px 4px 12px rgba(0, 0, 0, 0.1)",
            }}
          >
            {/* Название канала */}
            <div
              style={{
                fontSize: "18px",
                fontWeight: 600,
                color: "#CC5C47",
                textAlign: "center",
              }}
            >
              @{channel.username}
            </div>

            {/* Статус проверки (для отладки) */}
            {channel.isSubscribed === null && (
              <div
                style={{
                  fontSize: "12px",
                  color: "#999",
                  fontStyle: "italic",
                }}
              >
                Проверка...
              </div>
            )}
            {channel.isSubscribed === false && (
              <div
                style={{
                  fontSize: "12px",
                  color: "#CC5C47",
                }}
              >
                Требуется подписка
              </div>
            )}

            {/* Кнопки действий */}
            <div
              style={{
                display: "flex",
                flexDirection: "column",
                gap: "8px",
                width: "100%",
              }}
            >
              {/* Кнопка "Открыть канал" */}
              <button
                onClick={() => {
                  haptic.mediumTap();
                  openChannel(channel.username);
                }}
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  padding: "12px 24px",
                  backgroundColor: "#E98A65",
                  color: "#FFFFFF",
                  border: "none",
                  borderRadius: "12px",
                  fontSize: "14px",
                  fontWeight: 600,
                  cursor: "pointer",
                  boxShadow: "0px 4px 8px rgba(233, 138, 101, 0.3)",
                  transition: "all 0.2s ease",
                  width: "100%",
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = "#D77A55";
                  e.currentTarget.style.transform = "translateY(-2px)";
                  e.currentTarget.style.boxShadow = "0px 6px 12px rgba(233, 138, 101, 0.4)";
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = "#E98A65";
                  e.currentTarget.style.transform = "translateY(0)";
                  e.currentTarget.style.boxShadow = "0px 4px 8px rgba(233, 138, 101, 0.3)";
                }}
              >
                <ExternalLink size={18} />
                Открыть канал
              </button>
              
              {/* Кнопка "Скопировать ссылку" */}
              <button
                onClick={async () => {
                  haptic.lightTap();
                  const success = await copyChannelLink(channel.username);
                  if (success) {
                    setCopiedChannel(channel.username);
                    setTimeout(() => {
                      setCopiedChannel(null);
                    }, 2000);
                  }
                }}
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  gap: "8px",
                  padding: "10px 24px",
                  backgroundColor: "transparent",
                  color: copiedChannel === channel.username ? "#4CAF50" : "#CC5C47",
                  border: `2px solid ${copiedChannel === channel.username ? "#4CAF50" : "#CC5C47"}`,
                  borderRadius: "12px",
                  fontSize: "13px",
                  fontWeight: 500,
                  cursor: "pointer",
                  transition: "all 0.2s ease",
                  width: "100%",
                }}
                onMouseEnter={(e) => {
                  if (copiedChannel !== channel.username) {
                    e.currentTarget.style.backgroundColor = "#FFE0C5";
                  }
                }}
                onMouseLeave={(e) => {
                  if (copiedChannel !== channel.username) {
                    e.currentTarget.style.backgroundColor = "transparent";
                  }
                }}
              >
                {copiedChannel === channel.username ? (
                  <>
                    <Check size={16} />
                    Скопировано!
                  </>
                ) : (
                  <>
                    <Copy size={16} />
                    Скопировать ссылку
                  </>
                )}
              </button>
            </div>
          </div>
        ))}
      </div>

      {/* Кнопка "Проверить снова" */}
      <button
        onClick={() => {
          haptic.lightTap();
          checkSubscriptions(true); // Принудительная проверка (инвалидация кеша)
        }}
        style={{
          padding: "10px 24px",
          backgroundColor: "transparent",
          color: "#CC5C47",
          border: "2px solid #CC5C47",
          borderRadius: "8px",
          fontSize: "14px",
          fontWeight: 500,
          cursor: "pointer",
          transition: "all 0.2s ease",
        }}
        onMouseEnter={(e) => {
          e.currentTarget.style.backgroundColor = "#FFE0C5";
        }}
        onMouseLeave={(e) => {
          e.currentTarget.style.backgroundColor = "transparent";
        }}
      >
        Проверить снова
      </button>
    </div>
  );
};

export default ChannelSubscriptionCheck;
