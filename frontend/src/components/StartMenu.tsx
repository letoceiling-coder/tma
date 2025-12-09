import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { useChannelSubscription } from "@/hooks/useChannelSubscription";
import { haptic } from "@/lib/haptic";
import { ExternalLink } from "lucide-react";

interface StartMenuProps {
  channelUsernames: string[];
  onStartGame: () => void;
}

const StartMenu = ({ channelUsernames, onStartGame }: StartMenuProps) => {
  const navigate = useNavigate();
  const [showSubscription, setShowSubscription] = useState(false);
  
  const { isChecking, channels, allSubscribed, checkSubscriptions, openChannel } =
    useChannelSubscription({
      channelUsernames,
    });

  // Автоматически показываем экран проверки подписки при монтировании, если есть каналы
  useEffect(() => {
    if (channelUsernames.length > 0) {
      setShowSubscription(true);
      checkSubscriptions();
    } else {
      // Если каналов нет, сразу запускаем игру
      onStartGame();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [channelUsernames.length]);

  // Если все подписаны, автоматически запускаем игру
  useEffect(() => {
    if (allSubscribed && showSubscription && !isChecking && channels.length > 0) {
      onStartGame();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [allSubscribed, showSubscription, isChecking]);

  const handleSubscribe = () => {
    haptic.lightTap();
    setShowSubscription(true);
    checkSubscriptions(true); // Принудительная проверка
  };

  const handleHowToPlay = () => {
    haptic.lightTap();
    navigate("/how-to-play");
  };

  const handleStartGame = () => {
    haptic.success();
    onStartGame();
  };

  // Если каналов нет, сразу запускаем игру
  if (channelUsernames.length === 0) {
    return null; // Компонент не рендерится, так как onStartGame уже вызван
  }

  // Если показываем экран подписки
  if (showSubscription) {
    const unsubscribedChannels = channels.filter((ch) => ch.isSubscribed !== true);
    const allUnchecked = channels.every((ch) => ch.isSubscribed === null);
    const channelsToShow = allUnchecked ? channels : unsubscribedChannels;

    return (
      <div
        className="fixed inset-0 z-50 flex flex-col items-center justify-center px-6 py-8"
        style={{
          background: "#FECFB2",
          fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
          overflowY: "auto",
        }}
      >
        <h1
          style={{
            fontSize: "24px",
            fontWeight: 700,
            color: "#CC5C47",
            textAlign: "center",
            marginBottom: "32px",
            marginTop: 0,
          }}
        >
          Для участия подпишись на каналы
        </h1>

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
          {channelsToShow.map((channel, index) => (
            <button
              key={index}
              onClick={() => {
                haptic.lightTap();
                openChannel(channel.username);
                // Проверка произойдет автоматически через openChannel
              }}
              style={{
                width: "100%",
                padding: "16px",
                background: "#FFFFFF",
                borderRadius: "16px",
                border: "none",
                cursor: "pointer",
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                boxShadow: "0 2px 8px rgba(0,0,0,0.1)",
                transition: "all 0.2s",
              }}
              onMouseDown={(e) => (e.currentTarget.style.transform = "scale(0.98)")}
              onMouseUp={(e) => (e.currentTarget.style.transform = "scale(1)")}
              onMouseLeave={(e) => (e.currentTarget.style.transform = "scale(1)")}
            >
              <span style={{ fontSize: "16px", fontWeight: 600, color: "#CC5C47" }}>
                {channel.username}
              </span>
              <ExternalLink size={20} color="#CC5C47" />
            </button>
          ))}
        </div>

        <button
          onClick={() => {
            haptic.lightTap();
            checkSubscriptions(true); // Принудительная проверка (инвалидация кеша)
          }}
          disabled={isChecking}
          style={{
            width: "100%",
            maxWidth: "400px",
            padding: "16px",
            background: isChecking ? "#E07C63" : "#CC5C47",
            borderRadius: "16px",
            border: "none",
            cursor: isChecking ? "not-allowed" : "pointer",
            fontSize: "16px",
            fontWeight: 700,
            color: "#FFFFFF",
            boxShadow: "0 4px 12px rgba(204, 92, 71, 0.3)",
            transition: "all 0.2s",
            opacity: isChecking ? 0.7 : 1,
          }}
        >
          {isChecking ? "Проверка..." : "Проверить подписку"}
        </button>

        {!allSubscribed && (
          <button
            onClick={() => {
              haptic.lightTap();
              // Если пользователь нажимает "Назад", запускаем игру (пропускаем проверку)
              onStartGame();
            }}
            style={{
              marginTop: "16px",
              padding: "12px 24px",
              background: "transparent",
              border: "none",
              cursor: "pointer",
              fontSize: "14px",
              fontWeight: 600,
              color: "#CC5C47",
            }}
          >
            Пропустить
          </button>
        )}
      </div>
    );
  }

  // Основное меню больше не показываем - сразу проверяем подписку
  return null;
};

export default StartMenu;

