import { useState, useEffect } from "react";
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
  const [starsBalance, setStarsBalance] = useState<number | null>(null);
  const [isBalanceLoading, setIsBalanceLoading] = useState(false);
  const [hasInsufficientBalance, setHasInsufficientBalance] = useState(false);
  const [isInvoiceHandled, setIsInvoiceHandled] = useState(false); // Защита от двойной обработки
  const { initData: telegramInitData } = useTelegramWebApp();

  // Сброс состояния при открытии popup
  useEffect(() => {
    if (!isOpen) return;
    setIsInvoiceHandled(false);
  }, [isOpen]);

  // Проверка баланса звезд при открытии popup
  useEffect(() => {
    if (!isOpen) return;

    const checkStarsBalance = async () => {
      setIsBalanceLoading(true);
      setHasInsufficientBalance(false);

      try {
        // Используем Telegram WebApp SDK для получения баланса
        const tg = window.Telegram?.WebApp;
        
        if (tg && tg.initDataUnsafe?.user) {
          // Пытаемся получить баланс через cloudStorage
          const balance = await tg.cloudStorage?.get('stars_balance');
          
          if (balance !== null && balance !== undefined) {
            const balanceNum = parseInt(balance, 10);
            setStarsBalance(balanceNum);
            setHasInsufficientBalance(balanceNum < 50);
          } else {
            // Если баланс не доступен через cloudStorage, проверяем через API
            try {
              const apiUrl = import.meta.env.VITE_API_URL || '';
              const apiPath = apiUrl ? `${apiUrl}/api/payments/stars/balance` : `/api/payments/stars/balance`;
              
              const response = await fetch(apiPath, {
                method: 'GET',
                headers: {
                  'X-Telegram-Init-Data': telegramInitData || '',
                  'Accept': 'application/json',
                },
              });

              if (response.ok) {
                // Баланс будет проверен при открытии инвойса
                setStarsBalance(null);
              } else {
                setStarsBalance(null);
              }
            } catch (apiError) {
              console.warn('Failed to check balance via API:', apiError);
              setStarsBalance(null);
            }
          }
        }
      } catch (error) {
        console.warn('Failed to get stars balance:', error);
        // Продолжаем без проверки баланса - Telegram проверит при открытии инвойса
        setStarsBalance(null);
      } finally {
        setIsBalanceLoading(false);
      }
    };

    checkStarsBalance();
  }, [isOpen, telegramInitData]);

  // Обработка успешной оплаты через callback от Telegram
  // Примечание: Обработка происходит через callback в openInvoice, 
  // но оставляем обработчик событий как резервный
  useEffect(() => {
    const tg = window.Telegram?.WebApp;
    
    if (!tg) return;

    const handleInvoiceClosed = (event: any) => {
      // Защита от двойной обработки
      if (isInvoiceHandled) return;
      
      const status = event?.status || event;
      
      if (status === 'paid') {
        // Оплата успешна - начисляем билеты
        setIsInvoiceHandled(true);
        haptic.success();
        onExchange(20);
        toast.success('Успешно! Вам начислено 20 прокрутов.', { duration: 3000 });
        onClose();
      } else if (status === 'cancelled' || status === 'failed') {
        // Пользователь отменил или произошла ошибка
        setIsProcessing(false);
        haptic.error();
        toast.error('Оплата не прошла. Попробуйте ещё раз.', { duration: 3000 });
      } else {
        setIsProcessing(false);
      }
    };

    // Подписываемся на событие закрытия инвойса (резервный обработчик)
    tg.onEvent('invoiceClosed', handleInvoiceClosed);

    return () => {
      tg.offEvent('invoiceClosed', handleInvoiceClosed);
    };
  }, [onExchange, onClose]);

  // Обмен 50 звезд на 20 билетов через Telegram Stars Invoice
  const handleExchangeStars = async () => {
    if (isProcessing || hasInsufficientBalance) return;
    
    haptic.mediumTap();
    setIsProcessing(true);
    
    try {
      const tg = window.Telegram?.WebApp;
      if (!tg) {
        throw new Error('Telegram WebApp SDK не доступен');
      }

      // Получаем user_id из Telegram WebApp
      const telegramUser = tg.initDataUnsafe?.user;
      if (!telegramUser || !telegramUser.id) {
        throw new Error('Не удалось получить ID пользователя');
      }

      // Проверка баланса звёзд перед созданием инвойса
      try {
        // Пытаемся получить баланс через cloudStorage
        const balance = await tg.cloudStorage?.get('stars_balance');
        if (balance !== null && balance !== undefined) {
          const balanceNum = parseInt(balance, 10);
          if (balanceNum < 50) {
            setIsProcessing(false);
            setHasInsufficientBalance(true);
            setStarsBalance(balanceNum);
            haptic.error();
            toast.error('Недостаточно звёзд для обмена. Требуется 50 звёзд.', { duration: 3000 });
            return;
          }
        }

        // Дополнительная проверка через API
        const apiUrl = import.meta.env.VITE_API_URL || '';
        const balanceApiPath = apiUrl ? `${apiUrl}/api/payments/stars/balance` : `/api/payments/stars/balance`;
        
        const balanceResponse = await fetch(balanceApiPath, {
          method: 'GET',
          headers: {
            'X-Telegram-Init-Data': telegramInitData || '',
            'Accept': 'application/json',
          },
        });

        if (balanceResponse.ok) {
          const balanceData = await balanceResponse.json();
          // Если API вернул баланс и он меньше 50, прерываем
          if (balanceData.balance !== null && balanceData.balance !== undefined && balanceData.balance < 50) {
            setIsProcessing(false);
            setHasInsufficientBalance(true);
            setStarsBalance(balanceData.balance);
            haptic.error();
            toast.error('Недостаточно звёзд для обмена. Требуется 50 звёзд.', { duration: 3000 });
            return;
          }
        }
      } catch (balanceError) {
        console.warn('Failed to check balance before invoice:', balanceError);
        // Продолжаем - Telegram проверит баланс при открытии инвойса
      }

      // Создаем инвойс через backend
      const apiUrl = import.meta.env.VITE_API_URL || '';
      const apiPath = apiUrl ? `${apiUrl}/api/payments/stars/create-invoice` : `/api/payments/stars/create-invoice`;
      
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
        const errorMsg = data.message || data.error || 'Ошибка при создании платежа';
        
        // Если ошибка связана с недостаточным балансом
        if (errorMsg.includes('Недостаточно') || errorMsg.includes('insufficient') || errorMsg.includes('balance')) {
          setHasInsufficientBalance(true);
          haptic.error();
          toast.error('Недостаточно звёзд для обмена. Требуется 50 звёзд.', { duration: 3000 });
        } else {
          throw new Error(errorMsg);
        }
        setIsProcessing(false);
        return;
      }

      if (data.success && data.invoice_url) {
        // Открываем инвойс через Telegram WebApp SDK
        // Для Stars используем invoice_url напрямую
        // Это должно открыть системное окно оплаты Telegram Stars внутри Mini App
        console.log('Opening invoice:', data.invoice_url);
        tg.openInvoice(data.invoice_url, (status: string) => {
          console.log('Invoice closed with status:', status);
          // Защита от двойной обработки
          if (isInvoiceHandled) return;
          
          setIsProcessing(false);
          
          if (status === 'paid') {
            // Оплата успешна
            setIsInvoiceHandled(true);
            haptic.success();
            onExchange(20);
            toast.success('Успешно! Вам начислено 20 прокрутов.', { duration: 3000 });
            onClose();
          } else if (status === 'cancelled' || status === 'failed') {
            // Пользователь отменил или произошла ошибка
            haptic.error();
            toast.error('Оплата не была завершена. Попробуйте снова.', { duration: 3000 });
          } else {
            setIsProcessing(false);
          }
        });
      } else {
        throw new Error(data.message || 'Ошибка при создании платежа');
      }
    } catch (error: any) {
      setIsProcessing(false);
      haptic.error();
      const errorMessage = error.message || 'Ошибка при обмене звезд';
      toast.error(errorMessage, { duration: 3000 });
    }
  };

  if (!isOpen) return null;

  const isButtonDisabled = isProcessing || hasInsufficientBalance || isBalanceLoading;

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

          {/* Недостаточно звёзд сообщение */}
          {hasInsufficientBalance && (
            <div 
              style={{
                background: 'rgba(255, 0, 0, 0.1)',
                border: '1px solid rgba(255, 0, 0, 0.3)',
                borderRadius: '12px',
                padding: '12px',
                marginBottom: '16px',
                textAlign: 'center'
              }}
            >
              <p 
                style={{
                  fontSize: '14px',
                  color: '#8B0000',
                  fontWeight: 600,
                  margin: 0
                }}
              >
                Недостаточно звёзд для обмена. Требуется 50 звёзд.
              </p>
              {starsBalance !== null && (
                <p 
                  style={{
                    fontSize: '12px',
                    color: '#8B0000',
                    margin: '4px 0 0 0',
                    opacity: 0.8
                  }}
                >
                  У вас {starsBalance} звёзд
                </p>
              )}
            </div>
          )}

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

        {/* Exchange Button */}
        <button
          id="exchange-stars-btn"
          onClick={handleExchangeStars}
          disabled={isButtonDisabled}
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
            background: isButtonDisabled
              ? 'rgba(255,255,255,0.5)' 
              : 'linear-gradient(135deg, #FFFFFF 0%, #FFF8F5 100%)',
            fontSize: '17px',
            fontWeight: 700,
            color: isButtonDisabled ? '#999999' : '#E07C63',
            fontFamily: "'SF Pro Display', -apple-system, sans-serif",
            border: 'none',
            cursor: isButtonDisabled ? 'not-allowed' : 'pointer',
            boxShadow: isButtonDisabled ? 'none' : '0 4px 16px rgba(0,0,0,0.1)',
            transition: 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
            transform: isButtonDisabled ? 'scale(0.98)' : 'scale(1)',
            opacity: isButtonDisabled ? 0.6 : 1
          }}
        >
          <Star size={20} fill={isButtonDisabled ? "#999999" : "#FFD700"} color={isButtonDisabled ? "#999999" : "#FFD700"} />
          <span>
            {isProcessing 
              ? "Обработка..." 
              : isBalanceLoading
              ? "Проверка баланса..."
              : hasInsufficientBalance
              ? "Недостаточно звёзд"
              : "Обменять 50 звезд сейчас"
            }
          </span>
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
