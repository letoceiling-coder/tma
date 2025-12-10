import { useState, useEffect } from "react";
import { X } from "lucide-react";
import { haptic } from "@/lib/haptic";
import useTelegramWebApp from "@/hooks/useTelegramWebApp";

interface ReferralPopupProps {
  isOpen: boolean;
  onClose: () => void;
  onShare: () => void;
}

const ReferralPopup = ({ isOpen, onClose, onShare }: ReferralPopupProps) => {
  const { share, initData } = useTelegramWebApp();
  const [referralLink, setReferralLink] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  // Загружаем реферальную ссылку
  useEffect(() => {
    const loadReferralLink = async () => {
      if (!isOpen || !initData) return;
      
      try {
        setIsLoading(true);
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
      } finally {
        setIsLoading(false);
      }
    };

    loadReferralLink();
  }, [isOpen, initData]);

  const handleInvite = async () => {
    if (!referralLink) {
      return;
    }

    haptic.mediumTap();
    
    const shareText = "Присоединяйся ко мне в WOW Spin. Приглашай друзей и выигрывай призы. Вот ссылка для входа.";
    
    try {
      // Используем Telegram WebApp API для шаринга
      const shared = await share(referralLink, shareText);
      
      if (shared) {
        haptic.success();
        onShare();
        onClose();
        return;
      }
      
      // Fallback: пробуем нативный share API
      if (navigator.share) {
        await navigator.share({
          title: "WOW Spin",
          text: shareText,
          url: referralLink,
        });
        haptic.success();
        onShare();
        onClose();
        return;
      }
      
      // Final fallback: копируем в буфер обмена
      await navigator.clipboard.writeText(referralLink);
      haptic.success();
      onShare();
      onClose();
    } catch (error) {
      // Final fallback: копируем в буфер обмена
      try {
        await navigator.clipboard.writeText(referralLink);
        haptic.success();
        onShare();
        onClose();
      } catch (copyError) {
        haptic.error();
        console.error('Не удалось скопировать ссылку', copyError);
      }
    }
  };

  if (!isOpen) return null;

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 100,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '20px',
        background: 'rgba(0, 0, 0, 0.4)',
        animation: 'fadeIn 0.3s ease'
      }}
      onClick={onClose}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        style={{
          position: 'relative',
          width: '100%',
          maxWidth: '320px',
          background: 'linear-gradient(180deg, #FFFFFF 0%, #FFF8F5 100%)',
          borderRadius: '24px',
          padding: '36px 28px',
          textAlign: 'center',
          boxShadow: '0 20px 60px rgba(0, 0, 0, 0.2)',
          animation: 'scaleIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)'
        }}
      >
        {/* Close button */}
        <button
          onClick={() => {
            haptic.lightTap();
            onClose();
          }}
          style={{
            position: 'absolute',
            top: '14px',
            right: '14px',
            width: '34px',
            height: '34px',
            background: '#FFE8DC',
            border: 'none',
            borderRadius: '10px',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            transition: 'all 0.2s ease'
          }}
        >
          <X size={18} color="#E77D65" />
        </button>

        {/* Content */}
        <div
          style={{
            fontSize: '48px',
            marginBottom: '12px',
            animation: 'bounce 0.6s ease'
          }}
        >
          🎫
        </div>
        <p
          style={{
            fontSize: '18px',
            fontWeight: 600,
            color: '#333333',
            margin: '0 0 24px 0',
            lineHeight: 1.5,
            whiteSpace: 'pre-line',
            fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif"
          }}
        >
          У тебя закончились билеты. Пригласи друга и получи плюс один бесплатный прокрут.
        </p>
        
        <button
          onClick={handleInvite}
          disabled={!referralLink || isLoading}
          style={{
            width: '100%',
            padding: '14px 24px',
            marginTop: '8px',
            background: isLoading || !referralLink
              ? '#CCCCCC'
              : 'linear-gradient(135deg, #E07C63 0%, #D9644F 100%)',
            color: '#FFFFFF',
            borderRadius: '16px',
            border: 'none',
            cursor: isLoading || !referralLink ? 'not-allowed' : 'pointer',
            fontSize: '16px',
            fontWeight: 700,
            boxShadow: isLoading || !referralLink
              ? 'none'
              : '0 4px 12px rgba(224, 124, 99, 0.3)',
            transition: 'all 0.2s ease',
            fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
            opacity: isLoading || !referralLink ? 0.6 : 1,
          }}
          onMouseDown={(e) => {
            if (!isLoading && referralLink) {
              e.currentTarget.style.transform = 'scale(0.98)';
              e.currentTarget.style.opacity = '0.9';
            }
          }}
          onMouseUp={(e) => {
            if (!isLoading && referralLink) {
              e.currentTarget.style.transform = 'scale(1)';
              e.currentTarget.style.opacity = '1';
            }
          }}
          onMouseLeave={(e) => {
            if (!isLoading && referralLink) {
              e.currentTarget.style.transform = 'scale(1)';
              e.currentTarget.style.opacity = '1';
            }
          }}
        >
          {isLoading ? 'Загрузка...' : 'Пригласить друга'}
        </button>

        <style>
          {`
            @keyframes fadeIn {
              from { opacity: 0; }
              to { opacity: 1; }
            }
            @keyframes scaleIn {
              from { 
                opacity: 0; 
                transform: scale(0.8); 
              }
              to { 
                opacity: 1; 
                transform: scale(1); 
              }
            }
            @keyframes bounce {
              0%, 100% { transform: scale(1); }
              50% { transform: scale(1.2); }
            }
          `}
        </style>
      </div>
    </div>
  );
};

export default ReferralPopup;

