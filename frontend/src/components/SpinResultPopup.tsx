import { useEffect } from "react";
import { X } from "lucide-react";
import Confetti from "./Confetti";
import { haptic } from "@/lib/haptic";

interface SpinResultPopupProps {
  isOpen: boolean;
  onClose: () => void;
  result: number;
  prizeType: 'money' | 'ticket' | 'secret_box' | 'empty' | null;
  prizeValue: number;
  adminUsername: string | null;
  hasMoreTickets: boolean;
}

const SpinResultPopup = ({ isOpen, onClose, result, prizeType, prizeValue, adminUsername, hasMoreTickets }: SpinResultPopupProps) => {
  const isWin = result > 0 || result === -1;
  
  // Формируем сообщение о призе
  const getPrizeMessage = () => {
    const prizeValueNum = Number(prizeValue);
    
    // ВАЖНО: Для 300, 500 рублей, секретного бокса и подарка от спонсора - единое сообщение
    if (prizeType === 'money' && (prizeValueNum === 300 || prizeValueNum === 500)) {
      return `Поздравляем! Вы выиграли приз. Свяжитесь с администратором для получения.`;
    } else if (prizeType === 'money' && prizeValue > 0) {
      // Для других денежных призов (если есть)
      return `Поздравляем, вы выиграли ${prizeValue} рублей`;
    } else if (prizeType === 'ticket' && prizeValue > 0) {
      // Правильное склонение для билетов
      if (prizeValue === 1) {
        return `Поздравляем! Вы выиграли 1 дополнительный билет!`;
      } else {
        return `Поздравляем, вы выиграли ${prizeValue} дополнительных билетов`;
      }
    } else if (prizeType === 'secret_box') {
      return `Поздравляем! Вы выиграли приз. Свяжитесь с администратором для получения.`;
    } else if (prizeType === 'sponsor_gift') {
      return `Поздравляем! Вы выиграли приз. Свяжитесь с администратором для получения.`;
    }
    return '';
  };
  
  // Формируем ссылку на админа
  const getAdminLink = () => {
    if (!adminUsername || adminUsername.trim() === '') return null;
    const username = adminUsername.trim().startsWith('@') ? adminUsername.trim().slice(1) : adminUsername.trim();
    if (!username || username === '') return null;
    return `https://t.me/${username}?text=${encodeURIComponent('Здравствуйте, я выиграл приз в WOW Spin')}`;
  };
  
  const adminLink = getAdminLink();
  
  // Кнопка показывается СТРОГО только для: 300 рублей, 500 рублей, Secret Box, Подарок от спонсора
  // НЕ показывается для: пустого сектора, +1 билет
  const prizeValueNum = Number(prizeValue);
  const isEligiblePrize = prizeType !== 'empty' && 
    ((prizeType === 'money' && (prizeValueNum === 300 || prizeValueNum === 500)) || 
     prizeType === 'secret_box' ||
     prizeType === 'sponsor_gift');
  
  // Кнопка показывается только если есть adminUsername (иначе некуда вести)
  const showContactButton = isEligiblePrize && !!adminLink;

  // Trigger haptic feedback when popup opens
  useEffect(() => {
    if (isOpen) {
      if (isWin) {
        // Big win - multiple success haptics
        if (result >= 1000) {
          haptic.success();
          setTimeout(() => haptic.success(), 100);
        } else {
          haptic.success();
        }
      }
    }
  }, [isOpen, isWin, result]);

  if (!isOpen) return null;

  return (
    <>
      {/* Confetti for wins */}
      <Confetti isActive={isOpen && isWin} />
      
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
          {isWin ? (
            <>
              <div
                style={{
                  fontSize: '48px',
                  marginBottom: '12px',
                  animation: 'bounce 0.6s ease'
                }}
              >
                🎉
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
                {getPrizeMessage()}
              </p>
              
              {/* Кнопка "Получить" - показывается СТРОГО при выигрыше 300/500/secret_box */}
              {showContactButton && (
                <a
                  href={adminLink!}
                  target="_blank"
                  rel="noopener noreferrer"
                  onClick={() => haptic.lightTap()}
                  style={{
                    display: 'inline-block',
                    width: '100%',
                    padding: '14px 24px',
                    marginTop: '8px',
                    background: '#CC5C47',
                    color: '#FFFFFF',
                    borderRadius: '16px',
                    border: 'none',
                    cursor: 'pointer',
                    fontSize: '16px',
                    fontWeight: 700,
                    textDecoration: 'none',
                    boxShadow: '0 4px 12px rgba(204, 92, 71, 0.3)',
                    transition: 'all 0.2s ease',
                    fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif"
                  }}
                  onMouseDown={(e) => {
                    e.currentTarget.style.transform = 'scale(0.98)';
                    e.currentTarget.style.opacity = '0.9';
                  }}
                  onMouseUp={(e) => {
                    e.currentTarget.style.transform = 'scale(1)';
                    e.currentTarget.style.opacity = '1';
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.transform = 'scale(1)';
                    e.currentTarget.style.opacity = '1';
                  }}
                >
                  Получить
                </a>
              )}
            </>
          ) : (
            <>
              <div
                style={{
                  fontSize: '48px',
                  marginBottom: '12px'
                }}
              >
                😔
              </div>
              <h2
                style={{
                  fontSize: '22px',
                  fontWeight: 700,
                  color: '#333333',
                  margin: '0 0 12px 0',
                  fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif"
                }}
              >
                Не расстраивайся!
              </h2>
              <p
                style={{
                  fontSize: '16px',
                  fontWeight: 500,
                  color: '#777777',
                  margin: 0,
                  lineHeight: 1.5,
                  fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif"
                }}
              >
                {hasMoreTickets 
                  ? 'У тебя ещё есть попытки!'
                  : 'Пригласи друзей, чтобы получить больше билетов!'
                }
              </p>
            </>
          )}
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
    </>
  );
};

export default SpinResultPopup;