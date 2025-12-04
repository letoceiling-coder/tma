import { useState, useEffect, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import WheelComponent from "@/components/WheelComponent";
import BottomNav from "@/components/BottomNav";
import SecretGiftPopup from "@/components/SecretGiftPopup";
import SpinResultPopup from "@/components/SpinResultPopup";
import wowBunny from "@/assets/wow-bunny.png";
import { toast } from "sonner";
import { haptic } from "@/lib/haptic";
import useTelegramWebApp from "@/hooks/useTelegramWebApp";

interface WheelSector {
  id: number;
  sector_number: number;
  prize_type: 'money' | 'ticket' | 'secret_box' | 'empty';
  prize_value: number;
  icon_url: string | null;
  probability_percent: number;
}

interface WheelSegment {
  value: number;
  text: string;
  prizeType?: string;
  iconUrl?: string | null;
}

const MainWheel = () => {
  const navigate = useNavigate();
  const { userName, isReady: tgReady, initData: telegramInitData } = useTelegramWebApp();
  const [tickets, setTickets] = useState(0);
  const [isSpinning, setIsSpinning] = useState(false);
  const [rotation, setRotation] = useState(0); // Накопленный rotation для анимации
  const [lastSpinRotation, setLastSpinRotation] = useState<number | undefined>(undefined); // Последний rotation от сервера для определения сектора
  const [winningSectorNumber, setWinningSectorNumber] = useState<number | null>(null); // Номер выигрышного сектора (1-12) от сервера
  const [timeLeft, setTimeLeft] = useState(0);
  const [showGiftPopup, setShowGiftPopup] = useState(false);
  const [showResultPopup, setShowResultPopup] = useState(false);
  const [lastResult, setLastResult] = useState(0);
  const [isLoaded, setIsLoaded] = useState(false);
  const [wheelSegments, setWheelSegments] = useState<WheelSegment[]>([]);
  const [loadingSectors, setLoadingSectors] = useState(true);
  const [loadingTickets, setLoadingTickets] = useState(true);
  const [restoreIntervalSeconds, setRestoreIntervalSeconds] = useState(10800); // 3 часа по умолчанию
  const [restoreIntervalHours, setRestoreIntervalHours] = useState(3); // Часы для отображения

  // Загрузка секторов с сервера
  const loadWheelConfig = useCallback(async () => {
    try {
      setLoadingSectors(true);
      const apiUrl = import.meta.env.VITE_API_URL || '';
      const apiPath = apiUrl ? `${apiUrl}/api/wheel-config` : `/api/wheel-config`;
      
      const response = await fetch(apiPath, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error('Ошибка загрузки конфигурации рулетки');
      }

      const data = await response.json();
      
      // Сортируем секторы по sector_number (1-12)
      const sortedSectors = (data.sectors || []).sort((a: WheelSector, b: WheelSector) => 
        a.sector_number - b.sector_number
      );

      // Преобразуем секторы в формат для WheelComponent
      const segments: WheelSegment[] = sortedSectors.map((sector: WheelSector) => {
        let value = 0;
        let text = "0";
        
        if (sector.prize_type === 'money') {
          value = sector.prize_value;
          text = sector.prize_value.toString();
        } else if (sector.prize_type === 'ticket') {
          value = sector.prize_value || 1;
          text = `+${sector.prize_value || 1} билет`;
        } else if (sector.prize_type === 'secret_box') {
          value = -1; // Специальное значение для секретного бокса
          text = "??";
        }

        return {
          value,
          text,
          prizeType: sector.prize_type,
          iconUrl: sector.icon_url,
        };
      });

      setWheelSegments(segments);
    } catch (error) {
      console.error('Ошибка загрузки секторов:', error);
      toast.error('Ошибка загрузки конфигурации рулетки');
      // Используем пустой массив в случае ошибки
      setWheelSegments([]);
    } finally {
      setLoadingSectors(false);
    }
  }, []);

  // Загрузка билетов с сервера
  const loadTickets = useCallback(async () => {
    try {
      setLoadingTickets(true);
      const apiUrl = import.meta.env.VITE_API_URL || '';
      const apiPath = apiUrl ? `${apiUrl}/api/user/tickets` : `/api/user/tickets`;
      
      const response = await fetch(apiPath, {
        method: 'GET',
        headers: {
          'X-Telegram-Init-Data': telegramInitData,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error('Ошибка загрузки билетов');
      }

      const data = await response.json();
      setTickets(data.tickets_available || 0);
      
      // Сохраняем интервал восстановления
      if (data.restore_interval_seconds) {
        setRestoreIntervalSeconds(data.restore_interval_seconds);
      }
      if (data.restore_interval_hours) {
        setRestoreIntervalHours(data.restore_interval_hours);
      }
      
      // Устанавливаем время до следующего билета (округляем до целого)
      if (data.seconds_until_next_ticket !== null && data.seconds_until_next_ticket !== undefined) {
        setTimeLeft(Math.max(0, Math.floor(data.seconds_until_next_ticket)));
      } else {
        setTimeLeft(0);
      }
    } catch (error) {
      console.error('Ошибка загрузки билетов:', error);
      // В случае ошибки используем локальное состояние
      setTickets(3);
      setTimeLeft(0);
    } finally {
      setLoadingTickets(false);
    }
  }, []);

  // Animate on mount after Telegram is ready
  useEffect(() => {
    if (tgReady) {
      // Загружаем конфигурацию рулетки и билеты
      loadWheelConfig();
      loadTickets();
      
      // Small delay to ensure smooth animation
      requestAnimationFrame(() => {
        setIsLoaded(true);
      });
    }
  }, [tgReady, loadWheelConfig, loadTickets]);

  // Format seconds to HH:MM:SS
  const formatTime = (seconds: number) => {
    // Убеждаемся что это число и округляем до целого
    const totalSeconds = Math.floor(Math.max(0, Number(seconds) || 0));
    
    // Рассчитываем часы, минуты и секунды
    const hours = Math.floor(totalSeconds / 3600);
    const remainingAfterHours = totalSeconds % 3600;
    const mins = Math.floor(remainingAfterHours / 60);
    const secs = remainingAfterHours % 60;
    
    // Формат: ЧЧ:ММ:СС (всегда показываем часы)
    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  };

  // Get ticket word form
  const getTicketWord = (count: number) => {
    if (count === 1) return 'билет';
    if (count >= 2 && count <= 4) return 'билета';
    return 'билетов';
  };

  // Get hour word form
  const getHourWord = (count: number) => {
    if (count === 1) return 'час';
    if (count >= 2 && count <= 4) return 'часа';
    return 'часов';
  };

  // Периодическая синхронизация с сервером (каждые 30 секунд)
  useEffect(() => {
    if (!tgReady || tickets >= 3) return;

    const syncInterval = setInterval(() => {
      loadTickets(); // Синхронизируем время с сервером
    }, 30000); // Каждые 30 секунд

    return () => clearInterval(syncInterval);
  }, [tgReady, tickets, loadTickets]);

  // Синхронизация при возврате в приложение
  useEffect(() => {
    const handleVisibilityChange = () => {
      if (!document.hidden && tickets < 3) {
        loadTickets(); // Обновляем данные когда пользователь возвращается
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, [tickets, loadTickets]);

  // Timer effect - локальный обратный отсчет между синхронизациями
  useEffect(() => {
    if (tickets >= 3) {
      setTimeLeft(0);
      return; // Не запускаем таймер если билетов уже максимум
    }
    
    const timer = setInterval(() => {
      setTimeLeft((prev) => {
        const current = Math.floor(prev); // Убеждаемся что работаем с целыми числами
        if (current <= 1) {
          // Обновляем билеты с сервера когда таймер достигает 0
          loadTickets();
          return 0;
        }
        return current - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, [tickets, loadTickets]);

  const handleSpin = async () => {
    if (tickets <= 0) {
      haptic.warning();
      navigate("/friends");
      return;
    }

    if (isSpinning) return;

    const tg = window.Telegram?.WebApp;

    // Heavy haptic feedback for spin start
    haptic.heavyTap();
    setIsSpinning(true);
    setWinningSectorNumber(null); // Сбрасываем подсветку при новом спине

    try {
      const apiUrl = import.meta.env.VITE_API_URL || '';
      const apiPath = apiUrl ? `${apiUrl}/api/spin` : `/api/spin`;

      const response = await fetch(apiPath, {
        method: 'POST',
        headers: {
          'X-Telegram-Init-Data': telegramInitData,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.message || 'Ошибка прокрута рулетки');
      }

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.message || 'Ошибка прокрута рулетки');
      }

      // Обновляем билеты и таймер
      setTickets(data.tickets_available || 0);
      
      // Обновляем данные о времени до следующего билета (округляем до целого)
      if (data.seconds_until_next_ticket !== null && data.seconds_until_next_ticket !== undefined) {
        setTimeLeft(Math.max(0, Math.floor(data.seconds_until_next_ticket)));
      } else if (data.tickets_available === 0) {
        // Если билетов 0 и сервер не вернул время, устанавливаем полный интервал
        const intervalSeconds = data.restore_interval_seconds || restoreIntervalSeconds;
        setTimeLeft(intervalSeconds);
      }
      
      if (data.restore_interval_seconds) {
        setRestoreIntervalSeconds(data.restore_interval_seconds);
      }
      if (data.restore_interval_hours) {
        setRestoreIntervalHours(data.restore_interval_hours);
      }

      // Устанавливаем ротацию от сервера
      if (data.rotation !== undefined) {
        setLastSpinRotation(data.rotation);
        setRotation(data.rotation);
      }
      
      // Сохраняем номер выигрышного сектора от сервера
      if (data.sector?.sector_number) {
        setWinningSectorNumber(data.sector.sector_number);
      }

      // Определяем значение приза для отображения
      const prizeValue = data.sector?.prize_value || 0;
      const prizeType = data.sector?.prize_type;
      const spinId = data.spin_id;
      
      let resultValue = 0;
      if (prizeType === 'money') {
        resultValue = prizeValue;
      } else if (prizeType === 'ticket') {
        resultValue = prizeValue;
      } else if (prizeType === 'secret_box') {
        resultValue = -1; // Специальное значение для секретного бокса
      }

      // Ждем завершения анимации (4 секунды)
    setTimeout(async () => {
      setIsSpinning(false);
        setLastResult(resultValue);
      
      // Different haptic feedback based on result
        if (resultValue > 0 || resultValue === -1) {
        // Win - success notification
        haptic.success();
      } else {
        // No win - soft tap
        haptic.softTap();
      }
      
      setShowResultPopup(true);
        
        // Показываем сообщение о призе, если он был начислен
        if (data.prize_awarded) {
          if (prizeType === 'money') {
            toast.success(`Выиграно ${prizeValue}₽!`, { duration: 3000 });
          } else if (prizeType === 'ticket') {
            toast.success(`Получено ${prizeValue} билет(а)!`, { duration: 3000 });
          }
        }
        
        // Отправляем уведомление после завершения анимации
        try {
          const notifyPath = apiUrl ? `${apiUrl}/api/spin/notify` : `/api/spin/notify`;
          await fetch(notifyPath, {
            method: 'POST',
            headers: {
              'X-Telegram-Init-Data': telegramInitData,
              'Content-Type': 'application/json',
              'Accept': 'application/json',
            },
            body: JSON.stringify({ spin_id: spinId }),
          });
        } catch (notifyError) {
          console.error('Ошибка отправки уведомления:', notifyError);
          // Не блокируем работу приложения при ошибке уведомления
        }
    }, 4100);

    } catch (error: any) {
      console.error('Ошибка прокрута:', error);
      setIsSpinning(false);
      
      if (error.message?.includes('No tickets available')) {
        toast.error('У вас нет доступных билетов');
        navigate("/friends");
      } else {
        toast.error(error.message || 'Ошибка при прокруте рулетки');
      }
    }
  };

  const handleGiftExchange = () => {
    haptic.success();
    setShowGiftPopup(false);
    setTickets(tickets + 20);
    toast.success("Получено 20 билетов!", { duration: 3000 });
  };

  // Common button styles
  const buttonBaseStyle = {
    fontFamily: "'SF Pro Display', -apple-system, BlinkMacSystemFont, sans-serif",
    transition: 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
    WebkitTapHighlightColor: 'transparent',
  };

  // Показываем загрузку пока данные не загружены
  if (loadingSectors || wheelSegments.length === 0) {
    return (
      <div 
        className="relative w-full overflow-hidden flex items-center justify-center"
        style={{ 
          height: '100vh',
          background: 'linear-gradient(180deg, #F8A575 0%, #FDB083 100%)',
        }}
      >
        <p style={{ color: '#FFFFFF', fontSize: '16px' }}>Загрузка рулетки...</p>
      </div>
    );
  }

  return (
    <div 
      className="relative w-full overflow-hidden"
      style={{ 
        height: '100vh',
        maxHeight: '100vh',
        minHeight: '-webkit-fill-available',
        background: 'linear-gradient(180deg, #F8A575 0%, #FDB083 100%)',
        fontFamily: "'SF Pro Display', -apple-system, BlinkMacSystemFont, sans-serif",
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0
      }}
    >
      {/* Header - animated */}
      <div 
        className="absolute flex items-center gap-2"
        style={{ 
          top: '12px', 
          left: '16px',
          opacity: isLoaded ? 1 : 0,
          transform: isLoaded ? 'translateY(0)' : 'translateY(-10px)',
          transition: 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1) 0.1s'
        }}
      >
        <div 
          className="flex items-center justify-center overflow-hidden"
          style={{ 
            width: '32px',
            height: '32px',
            borderRadius: '50%',
            border: '2px solid rgba(255,255,255,0.3)',
            background: '#FFE4D6',
            boxShadow: '0 2px 8px rgba(0,0,0,0.1)'
          }}
        >
          <span style={{ fontSize: '18px' }}>🐰</span>
        </div>
        <span 
          style={{ 
            fontSize: '13px',
            fontWeight: 600,
            color: '#FFFFFF',
            textShadow: '0 1px 2px rgba(0,0,0,0.1)'
          }}
        >
          {userName}
        </span>
      </div>
      
      {/* How to play button - animated */}
      <button
        onClick={() => {
          haptic.lightTap();
          navigate("/how-to-play");
        }}
        className="absolute flex items-center justify-center"
        style={{
          ...buttonBaseStyle,
          top: '12px',
          right: '16px',
          height: '34px',
          padding: '0 16px',
          background: '#E07C63',
          borderRadius: '10px',
          border: 'none',
          cursor: 'pointer',
          gap: '6px',
          boxShadow: '0 2px 8px rgba(224, 124, 99, 0.3)',
          opacity: isLoaded ? 1 : 0,
          transform: isLoaded ? 'translateY(0) scale(1)' : 'translateY(-10px) scale(0.95)',
        }}
        onMouseDown={(e) => (e.currentTarget.style.transform = 'scale(0.95)')}
        onMouseUp={(e) => (e.currentTarget.style.transform = 'scale(1)')}
        onMouseLeave={(e) => (e.currentTarget.style.transform = 'scale(1)')}
      >
        <span style={{ fontSize: '13px', fontWeight: 600, color: '#FFFFFF' }}>
          Как играть?
        </span>
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
          <path d="M4 2L8 6L4 10" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      </button>

      {/* Gift bunny - animated */}
      <button 
        className="absolute flex flex-col items-center"
        style={{ 
          ...buttonBaseStyle,
          top: '54px', 
          right: '12px', 
          background: 'transparent', 
          border: 'none', 
          cursor: 'pointer',
          opacity: isLoaded ? 1 : 0,
          transform: isLoaded ? 'translateX(0)' : 'translateX(20px)',
        }}
        onClick={() => {
          haptic.lightTap();
          setShowGiftPopup(true);
        }}
        onMouseDown={(e) => (e.currentTarget.style.transform = 'scale(0.9)')}
        onMouseUp={(e) => (e.currentTarget.style.transform = 'scale(1)')}
        onMouseLeave={(e) => (e.currentTarget.style.transform = 'scale(1)')}
      >
        <img 
          src={wowBunny} 
          alt="WOW Bunny" 
          style={{ 
            width: '50px', 
            height: '72px', 
            objectFit: 'contain',
            filter: 'drop-shadow(0 4px 8px rgba(0,0,0,0.15))'
          }} 
        />
        <span style={{ 
          fontSize: '9px', 
          fontWeight: 700, 
          color: '#FFFFFF', 
          marginTop: '4px', 
          textTransform: 'uppercase',
          letterSpacing: '0.5px',
          textShadow: '0 1px 2px rgba(0,0,0,0.2)'
        }}>
          ПОДАРОК
        </span>
      </button>

      {/* Wheel - animated with scale on spin */}
      <div 
        className="absolute"
        style={{ 
          left: '50%', 
          top: '48%', 
          transform: `translate(-50%, -50%) scale(${isSpinning ? 1.02 : 1})`,
          transition: 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
          opacity: isLoaded ? 1 : 0,
          filter: `drop-shadow(0 8px 24px rgba(0,0,0,0.15))`
        }}
      >
        <WheelComponent
          segments={wheelSegments}
          rotation={rotation}
          lastSpinRotation={lastSpinRotation}
          winningSectorNumber={winningSectorNumber}
          onSpinComplete={(winningIndex) => {
            // Анимация завершена
          }}
        />
      </div>

      {/* Buttons Container - Perfectly Centered with Full Width */}
      <div 
        className="absolute animate-fade-in"
        style={{
          left: 0,
          right: 0,
          bottom: '80px',
          width: '100%',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          gap: '12px',
          opacity: isLoaded ? 1 : 0,
          transition: 'opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1) 0.3s',
          animationDelay: '0.3s',
          boxSizing: 'border-box',
          padding: 0,
          margin: 0
        }}
      >
        {/* Ticket status */}
        <div 
          style={{
            width: 'auto',
            minWidth: '280px',
            maxWidth: 'min(340px, calc(100vw - 32px))',
            height: '44px',
            background: tickets > 0 
              ? 'linear-gradient(135deg, #E8B5A0 0%, #D89A85 50%, #C98570 100%)' 
              : 'linear-gradient(135deg, #B8B8B8 0%, #A0A0A0 100%)',
            borderRadius: '16px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '0 24px',
            boxShadow: '0 4px 16px rgba(224, 124, 99, 0.3), inset 0 -2px 4px rgba(0,0,0,0.1)',
            transition: 'background 0.3s ease',
            boxSizing: 'border-box',
            margin: 0
          }}
        >
          <span style={{ 
            fontSize: '15px', 
            fontWeight: 600, 
            color: '#FFFFFF', 
            whiteSpace: 'nowrap',
            textShadow: '0 1px 2px rgba(0,0,0,0.1)',
            textAlign: 'center'
          }}>
            {loadingTickets ? (
              'Загрузка...'
            ) : tickets > 0 ? (
              `У вас ${tickets} ${getTicketWord(tickets)}`
            ) : (
              `Новый билет через ${formatTime(timeLeft)}`
            )}
          </span>
        </div>

        {/* Spin Button */}
        <button
          onClick={handleSpin}
          disabled={isSpinning || loadingTickets || tickets <= 0}
          style={{
            ...buttonBaseStyle,
            width: 'auto',
            minWidth: '280px',
            maxWidth: 'min(340px, calc(100vw - 32px))',
            height: '56px',
            background: 'linear-gradient(135deg, #E8B5A0 0%, #D89A85 50%, #C98570 100%)',
            boxShadow: isSpinning 
              ? '0 3px 12px rgba(224, 124, 99, 0.35), inset 0 -2px 4px rgba(0,0,0,0.1)' 
              : '0 6px 20px rgba(224, 124, 99, 0.4), inset 0 -2px 4px rgba(0,0,0,0.1)',
            borderRadius: '16px',
            fontSize: '18px',
            fontWeight: 700,
            color: '#FFFFFF',
            border: 'none',
            cursor: (isSpinning || loadingTickets || tickets <= 0) ? 'not-allowed' : 'pointer',
            opacity: (isSpinning || loadingTickets || tickets <= 0) ? 0.85 : 1,
            letterSpacing: '0.3px',
            textShadow: '0 2px 3px rgba(0,0,0,0.2)',
            textAlign: 'center',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '0 32px',
            transform: `scale(${isSpinning ? 0.97 : 1})`,
            transition: 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
            boxSizing: 'border-box',
            margin: 0
          }}
          onMouseDown={(e) => !isSpinning && !loadingTickets && tickets > 0 && (e.currentTarget.style.transform = 'scale(0.95)')}
          onMouseUp={(e) => !isSpinning && !loadingTickets && tickets > 0 && (e.currentTarget.style.transform = 'scale(1)')}
          onMouseLeave={(e) => !isSpinning && !loadingTickets && tickets > 0 && (e.currentTarget.style.transform = 'scale(1)')}
        >
          {isSpinning ? "Вращаем..." : loadingTickets ? "Загрузка..." : tickets <= 0 ? "Нет билетов" : "Вращать колесо"}
        </button>
      </div>

      <BottomNav />

      {/* Popups */}
      <SecretGiftPopup 
        isOpen={showGiftPopup}
        onClose={() => setShowGiftPopup(false)}
        onExchange={handleGiftExchange}
      />
      
      <SpinResultPopup
        isOpen={showResultPopup}
        onClose={() => setShowResultPopup(false)}
        result={lastResult}
        hasMoreTickets={tickets > 0}
      />
    </div>
  );
};

export default MainWheel;
