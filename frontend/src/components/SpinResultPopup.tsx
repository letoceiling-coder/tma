import { useEffect } from "react";
import { X } from "lucide-react";
import Confetti from "./Confetti";
import { haptic } from "@/lib/haptic";

interface SpinResultPopupProps {
  isOpen: boolean;
  onClose: () => void;
  result: number;
  hasMoreTickets: boolean;
}

const SpinResultPopup = ({ isOpen, onClose, result, hasMoreTickets }: SpinResultPopupProps) => {
  const isWin = result > 0;

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
              <h2
                style={{
                  fontSize: '24px',
                  fontWeight: 700,
                  color: '#333333',
                  margin: '0 0 16px 0',
                  fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif"
                }}
              >
                Поздравляем!
              </h2>
              <p
                style={{
                  fontSize: '20px',
                  fontWeight: 700,
                  color: '#E07C63',
                  margin: 0,
                  lineHeight: 1.4,
                  fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif"
                }}
              >
                Вы выиграли {result} ₽!
              </p>
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
                  ? 'У тебя еще есть билеты — попробуй снова!'
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