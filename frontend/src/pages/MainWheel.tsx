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

const MainWheel = () => {
  const navigate = useNavigate();
  const { userName, isReady: tgReady } = useTelegramWebApp();
  const [tickets, setTickets] = useState(3);
  const [isSpinning, setIsSpinning] = useState(false);
  const [rotation, setRotation] = useState(0);
  const [timeLeft, setTimeLeft] = useState(60);
  const [showGiftPopup, setShowGiftPopup] = useState(false);
  const [showResultPopup, setShowResultPopup] = useState(false);
  const [lastResult, setLastResult] = useState(0);
  const [isLoaded, setIsLoaded] = useState(false);

  // Animate on mount after Telegram is ready
  useEffect(() => {
    if (tgReady) {
      // Small delay to ensure smooth animation
      requestAnimationFrame(() => {
        setIsLoaded(true);
      });
    }
  }, [tgReady]);

  // Format seconds to MM:SS
  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  // Get ticket word form
  const getTicketWord = (count: number) => {
    if (count === 1) return 'билет';
    if (count >= 2 && count <= 4) return 'билета';
    return 'билетов';
  };

  // Timer effect
  useEffect(() => {
    const timer = setInterval(() => {
      setTimeLeft((prev) => {
        if (prev <= 1) {
          setTickets((t) => t + 1);
          return 60;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(timer);
  }, []);

  const wheelSegments = [
    { value: 0, text: "0" },
    { value: 2000, text: "2000" },
    { value: 0, text: "0" },
    { value: 300, text: "300" },
    { value: 500, text: "500" },
    { value: 0, text: "0" },
    { value: 0, text: "0" },
    { value: 300, text: "300" },
    { value: 300, text: "300" },
    { value: 0, text: "0" },
    { value: 1000, text: "1000" },
    { value: 0, text: "0" },
  ];

  const handleSpin = () => {
    if (tickets <= 0) {
      haptic.warning();
      navigate("/friends");
      return;
    }

    if (isSpinning) return;

    // Heavy haptic feedback for spin start
    haptic.heavyTap();

    setIsSpinning(true);
    setTickets(tickets - 1);

    const zeroIndices = wheelSegments.map((s, i) => s.value === 0 ? i : -1).filter(i => i !== -1);
    const prizeIndices = wheelSegments.map((s, i) => s.value > 0 ? i : -1).filter(i => i !== -1);
    
    const winningIndex = Math.random() < 0.8 
      ? zeroIndices[Math.floor(Math.random() * zeroIndices.length)]
      : prizeIndices[Math.floor(Math.random() * prizeIndices.length)];
    
    const result = wheelSegments[winningIndex];
    
    const segmentAngle = 360 / wheelSegments.length;
    const baseRotation = 360 * 5;
    const targetRotation = baseRotation + (360 - (winningIndex * segmentAngle + segmentAngle / 2));
    
    setRotation(prev => prev + targetRotation);

    setTimeout(() => {
      setIsSpinning(false);
      setLastResult(result.value);
      
      // Different haptic feedback based on result
      if (result.value > 0) {
        // Win - success notification
        haptic.success();
      } else {
        // No win - soft tap
        haptic.softTap();
      }
      
      setShowResultPopup(true);
    }, 4100);
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
          onSpinComplete={(winningIndex) => {
            console.log("WIN:", wheelSegments[winningIndex]);
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
            {tickets > 0 
              ? `У вас ${tickets} ${getTicketWord(tickets)}`
              : `Новый билет через ${formatTime(timeLeft)}`
            }
          </span>
        </div>

        {/* Spin Button */}
        <button
          onClick={handleSpin}
          disabled={isSpinning}
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
            cursor: isSpinning ? 'not-allowed' : 'pointer',
            opacity: isSpinning ? 0.85 : 1,
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
          onMouseDown={(e) => !isSpinning && (e.currentTarget.style.transform = 'scale(0.95)')}
          onMouseUp={(e) => !isSpinning && (e.currentTarget.style.transform = 'scale(1)')}
          onMouseLeave={(e) => !isSpinning && (e.currentTarget.style.transform = 'scale(1)')}
        >
          {isSpinning ? "Вращаем..." : "Вращать колесо"}
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