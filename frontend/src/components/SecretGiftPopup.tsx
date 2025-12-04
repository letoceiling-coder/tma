import { useState } from "react";
import { X, Star } from "lucide-react";
import { toast } from "sonner";
import popupBunnyHeart from "@/assets/popup-bunny-heart.png";
import { haptic } from "@/lib/haptic";
import useTelegramWebApp from "@/hooks/useTelegramWebApp";

interface SecretGiftPopupProps {
  isOpen: boolean;
  onClose: () => void;
  onExchange: (ticketsReceived: number) => void;
}

const SecretGiftPopup = ({ isOpen, onClose, onExchange }: SecretGiftPopupProps) => {
  const [isProcessing, setIsProcessing] = useState(false);
  const { initData: telegramInitData } = useTelegramWebApp();

  if (!isOpen) return null;

  // Обмен 50 звезд на 20 билетов
  const handleExchangeStars = async () => {
    if (isProcessing) return;
    
    haptic.mediumTap();
    setIsProcessing(true);
    
    try {
      const apiUrl = import.meta.env.VITE_API_URL || '';
      const apiPath = apiUrl ? `${apiUrl}/api/stars/exchange` : `/api/stars/exchange`;
      
      const response = await fetch(apiPath, {
        method: 'POST',
        headers: {
          'X-Telegram-Init-Data': telegramInitData || '',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || data.error || 'Ошибка при обмене звезд');
      }

      if (data.success) {
        haptic.success();
        onExchange(data.tickets_received || 20);
        toast.success(`Получено ${data.tickets_received || 20} билетов!`, { duration: 3000 });
        onClose();
      } else {
        throw new Error(data.message || 'Ошибка при обмене звезд');
      }
    } catch (error: any) {
      setIsProcessing(false);
      haptic.error();
      const errorMessage = error.message || 'Ошибка при обмене звезд';
      toast.error(errorMessage, { duration: 3000 });
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <div 
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 9999,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'rgba(0, 0, 0, 0.5)',
        backdropFilter: 'blur(4px)',
        padding: '16px',
        animation: 'fadeIn 0.3s ease'
      }}
      onClick={onClose}
    >
      <div 
        onClick={(e) => e.stopPropagation()}
        style={{
          width: '100%',
          maxWidth: '360px',
          animation: 'scaleIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)'
        }}
      >
        {/* Main Card */}
        <div 
          style={{ 
            background: 'linear-gradient(180deg, #F2B294 0%, #E59678 100%)',
            borderRadius: '24px',
            padding: '32px 24px 24px',
            position: 'relative',
            boxShadow: '0 20px 60px rgba(0, 0, 0, 0.25)'
          }}
        >
          {/* Close button */}
          <button
            onClick={() => {
              haptic.lightTap();
              onClose();
            }}
            aria-label="Закрыть"
            style={{
              position: 'absolute',
              top: '16px',
              right: '16px',
              width: '36px',
              height: '36px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              background: 'rgba(255,255,255,0.3)',
              border: 'none',
              borderRadius: '10px',
              cursor: 'pointer',
              transition: 'all 0.2s ease'
            }}
          >
            <X size={20} color="#8B5A42" />
          </button>

          {/* Title */}
          <h2 
            style={{
              fontSize: '26px',
              fontWeight: 700,
              color: '#5D3A2B',
              textAlign: 'center',
              marginBottom: '12px',
              fontFamily: "'SF Pro Display', -apple-system, sans-serif",
              lineHeight: 1.2
            }}
          >
            Секретный подарок<br />от кролика
          </h2>

          {/* Description */}
          <p 
            style={{
              fontSize: '15px',
              color: '#7D5A4A',
              textAlign: 'center',
              marginBottom: '20px',
              fontFamily: "'SF Pro Display', -apple-system, sans-serif",
              lineHeight: 1.5
            }}
          >
            Обменяй 50 звезд на 20 прокруток<br />рулетки и приблизься к своему призу
          </p>

          {/* Info badge */}
          <div 
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              gap: '8px',
              background: 'rgba(255,255,255,0.5)',
              borderRadius: '100px',
              padding: '12px 20px',
              margin: '0 auto 24px',
              width: 'fit-content'
            }}
          >
            <Star size={16} fill="#FFD700" color="#FFD700" />
            <span 
              style={{
                fontSize: '14px',
                color: '#5D3A2B',
                fontWeight: 600
              }}
            >
              Звезды спишутся автоматически
            </span>
          </div>

          {/* Bunny Image */}
          <div style={{ display: 'flex', justifyContent: 'center' }}>
            <img 
              src={popupBunnyHeart}
              alt="Кролик с сердцем"
              style={{
                width: '180px',
                height: 'auto',
                objectFit: 'contain',
                filter: 'drop-shadow(0 8px 16px rgba(0,0,0,0.15))'
              }}
            />
          </div>
        </div>

        {/* Exchange Button - TG Stars ready */}
        <button
          id="exchange-stars-btn"
          onClick={handleExchangeStars}
          disabled={isProcessing}
          aria-label="Обменять 50 звезд на 20 прокруток"
          style={{
            width: '100%',
            marginTop: '16px',
            padding: '18px 24px',
            borderRadius: '16px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '10px',
            background: isProcessing 
              ? 'rgba(255,255,255,0.7)' 
              : 'linear-gradient(135deg, #FFFFFF 0%, #FFF8F5 100%)',
            fontSize: '17px',
            fontWeight: 700,
            color: '#E07C63',
            fontFamily: "'SF Pro Display', -apple-system, sans-serif",
            border: 'none',
            cursor: isProcessing ? 'not-allowed' : 'pointer',
            boxShadow: '0 4px 16px rgba(0,0,0,0.1)',
            transition: 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
            transform: isProcessing ? 'scale(0.98)' : 'scale(1)'
          }}
        >
          <Star size={20} fill="#FFD700" color="#FFD700" />
          <span>{isProcessing ? "Обработка..." : "Обменять 50 звезд сейчас"}</span>
        </button>
      </div>

      <style>
        {`
          @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
          }
          @keyframes scaleIn {
            from { 
              opacity: 0; 
              transform: scale(0.9) translateY(20px); 
            }
            to { 
              opacity: 1; 
              transform: scale(1) translateY(0); 
            }
          }
        `}
      </style>
    </div>
  );
};

export default SecretGiftPopup;