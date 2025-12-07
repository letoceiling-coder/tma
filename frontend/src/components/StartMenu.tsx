import { useState } from "react";
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
      onSubscriptionConfirmed: () => {
        setShowSubscription(false);
      },
    });

  const handleSubscribe = () => {
    haptic.lightTap();
    setShowSubscription(true);
    checkSubscriptions();
  };

  const handleHowToPlay = () => {
    haptic.lightTap();
    navigate("/how-to-play");
  };

  const handleStartGame = () => {
    haptic.success();
    onStartGame();
  };

  // Если показываем экран подписки
  if (showSubscription && !allSubscribed) {
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
            checkSubscriptions();
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

        <button
          onClick={() => {
            haptic.lightTap();
            setShowSubscription(false);
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
          Назад
        </button>
      </div>
    );
  }

  // Основное меню
  return (
    <div
      className="fixed inset-0 z-50 flex flex-col items-center justify-center px-6"
      style={{
        background: "linear-gradient(180deg, #F8A575 0%, #FDB083 100%)",
        fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
      }}
    >
      <div
        style={{
          width: "100%",
          maxWidth: "400px",
          display: "flex",
          flexDirection: "column",
          gap: "16px",
        }}
      >
        {/* Кнопка подписки (если есть каналы) */}
        {channelUsernames.length > 0 && (
          <button
            onClick={handleSubscribe}
            style={{
              width: "100%",
              padding: "20px",
              background: "#E07C63",
              borderRadius: "20px",
              border: "none",
              cursor: "pointer",
              fontSize: "18px",
              fontWeight: 700,
              color: "#FFFFFF",
              boxShadow: "0 4px 16px rgba(224, 124, 99, 0.4)",
              transition: "all 0.2s",
            }}
            onMouseDown={(e) => (e.currentTarget.style.transform = "scale(0.98)")}
            onMouseUp={(e) => (e.currentTarget.style.transform = "scale(1)")}
            onMouseLeave={(e) => (e.currentTarget.style.transform = "scale(1)")}
          >
            📢 Подписаться на каналы
          </button>
        )}

        {/* Кнопка "Как играть" */}
        <button
          onClick={handleHowToPlay}
          style={{
            width: "100%",
            padding: "20px",
            background: "#FFFFFF",
            borderRadius: "20px",
            border: "none",
            cursor: "pointer",
            fontSize: "18px",
            fontWeight: 700,
            color: "#CC5C47",
            boxShadow: "0 4px 16px rgba(0,0,0,0.1)",
            transition: "all 0.2s",
          }}
          onMouseDown={(e) => (e.currentTarget.style.transform = "scale(0.98)")}
          onMouseUp={(e) => (e.currentTarget.style.transform = "scale(1)")}
          onMouseLeave={(e) => (e.currentTarget.style.transform = "scale(1)")}
        >
          📖 Как играть
        </button>

        {/* Кнопка запуска игры */}
        <button
          onClick={handleStartGame}
          style={{
            width: "100%",
            padding: "24px",
            background: "#CC5C47",
            borderRadius: "20px",
            border: "none",
            cursor: "pointer",
            fontSize: "20px",
            fontWeight: 700,
            color: "#FFFFFF",
            boxShadow: "0 6px 20px rgba(204, 92, 71, 0.5)",
            transition: "all 0.2s",
            marginTop: "8px",
          }}
          onMouseDown={(e) => (e.currentTarget.style.transform = "scale(0.98)")}
          onMouseUp={(e) => (e.currentTarget.style.transform = "scale(1)")}
          onMouseLeave={(e) => (e.currentTarget.style.transform = "scale(1)")}
        >
          🎰 Запустить игру
        </button>
      </div>
    </div>
  );
};

export default StartMenu;

