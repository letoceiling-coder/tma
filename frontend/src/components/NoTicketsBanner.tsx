import { useState, useEffect } from "react";
import { toast } from "sonner";
import { haptic } from "@/lib/haptic";
import useTelegramWebApp from "@/hooks/useTelegramWebApp";

interface NoTicketsBannerProps {
  isVisible: boolean;
}

const NoTicketsBanner = ({ isVisible }: NoTicketsBannerProps) => {
  const { share, initData } = useTelegramWebApp();
  const [referralLink, setReferralLink] = useState<string | null>(null);
  const [isCopied, setIsCopied] = useState(false);

  // Загружаем реферальную ссылку
  useEffect(() => {
    const loadReferralLink = async () => {
      try {
        const apiUrl = import.meta.env.VITE_API_URL || '';
        const apiPath = apiUrl ? `${apiUrl}/api/referral/link` : `/api/referral/link`;
        
        const response = await fetch(apiPath, {
          method: 'GET',
          headers: {
            'X-Telegram-Init-Data': initData,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          },
        });

        if (response.ok) {
          const data = await response.json();
          if (data.referral_link) {
            setReferralLink(data.referral_link);
          }
        }
      } catch (error) {
        console.error('Ошибка загрузки реферальной ссылки:', error);
      }
    };

    if (isVisible && initData) {
      loadReferralLink();
    }
  }, [isVisible, initData]);

  const handleInvite = async () => {
    if (!referralLink) {
      toast.error('Ссылка не загружена');
      return;
    }

    haptic.mediumTap();
    
    try {
      // Сначала пробуем поделиться через Telegram
      const shared = await share(referralLink, "Присоединяйся к WOW Рулетке! 🎰");
      
      if (shared) {
        haptic.success();
        return;
      }
      
      // Пробуем нативный share API
      if (navigator.share) {
        await navigator.share({
          title: "WOW Рулетка",
          text: "Присоединяйся к WOW Рулетке! Крути колесо и выигрывай призы! 🎰",
          url: referralLink,
        });
        haptic.success();
        return;
      }
      
      // Fallback: копируем в буфер обмена
      await navigator.clipboard.writeText(referralLink);
      setIsCopied(true);
      haptic.success();
      toast.success("Ссылка скопирована!", { duration: 2000 });
      setTimeout(() => setIsCopied(false), 2000);
    } catch (error) {
      // Final fallback: копируем в буфер обмена
      try {
        await navigator.clipboard.writeText(referralLink);
        setIsCopied(true);
        haptic.success();
        toast.success("Ссылка скопирована!", { duration: 2000 });
        setTimeout(() => setIsCopied(false), 2000);
      } catch (copyError) {
        haptic.error();
        toast.error("Не удалось скопировать ссылку");
      }
    }
  };

  if (!isVisible) return null;

  return (
    <div
      style={{
        width: 'auto',
        minWidth: '280px',
        maxWidth: 'min(340px, calc(100vw - 32px))',
        background: 'linear-gradient(135deg, #FFE4D6 0%, #FFD4C0 100%)',
        borderRadius: '16px',
        padding: '16px 20px',
        boxShadow: '0 4px 16px rgba(224, 124, 99, 0.25), inset 0 -2px 4px rgba(0,0,0,0.05)',
        border: '2px solid rgba(224, 124, 99, 0.2)',
        display: 'flex',
        flexDirection: 'column',
        gap: '12px',
        boxSizing: 'border-box',
        margin: 0,
        opacity: isVisible ? 1 : 0,
        transform: isVisible ? 'translateY(0)' : 'translateY(-10px)',
        transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
      }}
    >
      <p
        style={{
          fontSize: '14px',
          fontWeight: 500,
          color: '#E07C63',
          margin: 0,
          lineHeight: '1.4',
          textAlign: 'center',
        }}
      >
        У тебя закончились билеты. Пригласив друзей, ты получишь +1 прокрут!
      </p>
      
      <button
        onClick={handleInvite}
        disabled={!referralLink}
        style={{
          width: '100%',
          padding: '12px 20px',
          borderRadius: '12px',
          background: isCopied
            ? 'linear-gradient(135deg, #22C55E 0%, #16A34A 100%)'
            : 'linear-gradient(135deg, #E07C63 0%, #D9644F 100%)',
          border: 'none',
          cursor: referralLink ? 'pointer' : 'not-allowed',
          fontSize: '15px',
          fontWeight: 700,
          color: '#FFFFFF',
          textAlign: 'center',
          boxShadow: isCopied
            ? '0 3px 12px rgba(34, 197, 94, 0.3)'
            : '0 4px 12px rgba(224, 124, 99, 0.35)',
          transition: 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
          opacity: referralLink ? 1 : 0.6,
          WebkitTapHighlightColor: 'transparent',
        }}
        onMouseDown={(e) => {
          if (referralLink) {
            e.currentTarget.style.transform = 'scale(0.97)';
          }
        }}
        onMouseUp={(e) => {
          if (referralLink) {
            e.currentTarget.style.transform = 'scale(1)';
          }
        }}
        onMouseLeave={(e) => {
          if (referralLink) {
            e.currentTarget.style.transform = 'scale(1)';
          }
        }}
      >
        {isCopied ? '✓ Скопировано!' : 'Пригласить друга'}
      </button>
    </div>
  );
};

export default NoTicketsBanner;
