import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { Copy, Check } from "lucide-react";
import BottomNav from "@/components/BottomNav";
import { toast } from "sonner";
import friendsInvite from "@/assets/friends-invite.png";
import friendsTickets from "@/assets/friends-tickets.png";
import { haptic } from "@/lib/haptic";
import useTelegramWebApp from "@/hooks/useTelegramWebApp";

const Friends = () => {
  const navigate = useNavigate();
  const { userName, share, isReady: tgReady } = useTelegramWebApp();
  const [isCopied, setIsCopied] = useState(false);
  const [isLoaded, setIsLoaded] = useState(false);
  const referralLink = "https://t.me/wow_roulette_bot?start=ref123456";

  useEffect(() => {
    if (tgReady) {
      requestAnimationFrame(() => {
        setIsLoaded(true);
      });
    }
  }, [tgReady]);

  const handleInvite = async () => {
    haptic.mediumTap();
    
    try {
      // Try Telegram share first
      const shared = share(referralLink, "Присоединяйся к WOW Рулетке! 🎰");
      
      if (shared) {
        haptic.success();
        return;
      }
      
      // Try native share
      if (navigator.share) {
        await navigator.share({
          title: "WOW Рулетка",
          text: "Присоединяйся к WOW Рулетке! Крути колесо и выигрывай призы! 🎰",
          url: referralLink,
        });
        haptic.success();
        return;
      }
      
      // Fallback: copy to clipboard
      await navigator.clipboard.writeText(referralLink);
      setIsCopied(true);
      haptic.success();
      toast.success("Ссылка скопирована!", { duration: 2000 });
      setTimeout(() => setIsCopied(false), 2000);
    } catch (error) {
      // Final fallback
      await navigator.clipboard.writeText(referralLink);
      setIsCopied(true);
      haptic.success();
      toast.success("Ссылка скопирована!", { duration: 2000 });
      setTimeout(() => setIsCopied(false), 2000);
    }
  };

  return (
    <div 
      className="relative w-full overflow-hidden"
      style={{ 
        height: '100vh',
        maxHeight: '100vh',
        minHeight: '-webkit-fill-available',
        background: 'linear-gradient(180deg, #FCDAC6 0%, #F8B89A 100%)',
        fontFamily: "'SF Pro Display', -apple-system, BlinkMacSystemFont, sans-serif",
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0
      }}
    >
      {/* Header */}
      <div 
        className="absolute top-3 left-4 flex items-center gap-2"
        style={{
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
      
      {/* How to play button */}
      <button
        onClick={() => {
          haptic.lightTap();
          navigate("/how-to-play");
        }}
        className="absolute flex items-center justify-center"
        style={{
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
          transform: isLoaded ? 'translateY(0)' : 'translateY(-10px)',
          transition: 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1) 0.15s'
        }}
      >
        <span style={{ fontSize: '13px', fontWeight: 600, color: '#FFFFFF' }}>
          Как играть?
        </span>
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
          <path d="M4 2L8 6L4 10" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      </button>

      {/* Main Content - Images */}
      <div 
        className="absolute left-1/2 flex flex-col items-center justify-center gap-6"
        style={{ 
          top: '50%',
          transform: 'translate(-50%, -55%)',
          width: '100%',
          padding: '0 24px',
          opacity: isLoaded ? 1 : 0,
          transition: 'opacity 0.5s ease 0.2s'
        }}
      >
        <img 
          src={friendsInvite}
          alt="Приглашай друзей"
          style={{ 
            width: '85vw',
            maxWidth: '340px',
            height: 'auto',
            filter: 'drop-shadow(0 4px 12px rgba(0,0,0,0.1))'
          }}
        />
        <img 
          src={friendsTickets}
          alt="Получай билеты"
          style={{ 
            width: '85vw',
            maxWidth: '340px',
            height: 'auto',
            filter: 'drop-shadow(0 4px 12px rgba(0,0,0,0.1))'
          }}
        />
      </div>

      {/* Invite Button */}
      <div 
        className="absolute left-1/2"
        style={{ 
          bottom: '88px', 
          transform: 'translateX(-50%)',
          width: '90vw', 
          maxWidth: '360px',
          opacity: isLoaded ? 1 : 0,
          transition: 'all 0.5s ease 0.3s'
        }}
      >
        <button
          onClick={handleInvite}
          style={{
            width: '100%',
            padding: '16px 24px',
            borderRadius: '14px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '12px',
            background: 'linear-gradient(135deg, #FFFFFF 0%, #FFF8F5 100%)',
            fontSize: '16px',
            fontWeight: 700,
            color: '#E07C63',
            border: 'none',
            cursor: 'pointer',
            boxShadow: '0 4px 16px rgba(0,0,0,0.1)',
            transition: 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
            WebkitTapHighlightColor: 'transparent'
          }}
          onMouseDown={(e) => (e.currentTarget.style.transform = 'scale(0.97)')}
          onMouseUp={(e) => (e.currentTarget.style.transform = 'scale(1)')}
          onMouseLeave={(e) => (e.currentTarget.style.transform = 'scale(1)')}
        >
          <span>{isCopied ? "Скопировано!" : "Пригласить друга"}</span>
          <div 
            style={{ 
              width: '36px',
              height: '36px',
              borderRadius: '10px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              background: isCopied ? '#22C55E' : '#E07C63',
              transition: 'background 0.2s ease'
            }}
          >
            {isCopied ? (
              <Check size={18} color="white" />
            ) : (
              <Copy size={18} color="white" />
            )}
          </div>
        </button>
      </div>

      <BottomNav />
    </div>
  );
};

export default Friends;