import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import BottomNav from "@/components/BottomNav";
import { toast } from "sonner";
import leaderboardBunny from "@/assets/leaderboard-bunny-1500.png";
import leaderboardTopText from "@/assets/leaderboard-top-text.png";
import btn5000 from "@/assets/btn-5000.svg";
import { haptic } from "@/lib/haptic";
import useTelegramWebApp from "@/hooks/useTelegramWebApp";

interface LeaderEntry {
  rank: number;
  name: string;
  referrals: number;
  prize: string;
}

const Leaderboard = () => {
  const navigate = useNavigate();
  const { userName, share } = useTelegramWebApp();
  const [isCopied, setIsCopied] = useState(false);
  const referralLink = "https://t.me/wow_roulette_bot?start=ref123456";

  const leaders: LeaderEntry[] = [
    { rank: 1, name: "Александр", referrals: 145, prize: "5000 ₽" },
    { rank: 2, name: "Мария", referrals: 132, prize: "3000 ₽" },
    { rank: 3, name: "Дмитрий", referrals: 118, prize: "2000 ₽" },
    { rank: 4, name: "Анна", referrals: 98, prize: "1000 ₽" },
    { rank: 5, name: "Сергей", referrals: 87, prize: "500 ₽" },
    { rank: 6, name: "Елена", referrals: 76, prize: "500 ₽" },
    { rank: 7, name: "Иван", referrals: 65, prize: "300 ₽" },
    { rank: 8, name: "Ольга", referrals: 54, prize: "300 ₽" },
    { rank: 9, name: "Михаил", referrals: 43, prize: "300 ₽" },
    { rank: 10, name: "Татьяна", referrals: 32, prize: "300 ₽" },
  ];

  const handleInvite = async () => {
    haptic.mediumTap();
    
    // Try Telegram share first
    const shared = share(referralLink, "Присоединяйся к WOW Рулетке! 🎰");
    if (shared) {
      haptic.success();
      return;
    }
    
    // Fallback: copy to clipboard
    try {
      await navigator.clipboard.writeText(referralLink);
      setIsCopied(true);
      haptic.success();
      toast.success("Ссылка скопирована!", { duration: 2000 });
      setTimeout(() => setIsCopied(false), 2000);
    } catch (err) {
      console.error("Failed to copy:", err);
    }
  };

  return (
    <div 
      className="relative w-full overflow-hidden"
      style={{ 
        height: '100vh',
        maxHeight: '100vh',
        minHeight: '-webkit-fill-available',
        background: 'linear-gradient(180deg, #FDD4C2 0%, #F8B89A 100%)',
        fontFamily: "'Nunito', -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
        position: 'fixed',
        top: 0,
        left: 0,
        right: 0,
        bottom: 0
      }}
    >
      {/* Header */}
      <div className="absolute top-3 left-4 flex items-center gap-2">
        <div 
          className="w-8 h-8 rounded-full flex items-center justify-center overflow-hidden"
          style={{ 
            border: '1px solid #F7785B',
            background: '#FFE4D6'
          }}
        >
          <span className="text-lg">🐰</span>
        </div>
        <span 
          className="text-white text-xs font-medium"
          style={{ 
            fontSize: '12px',
            fontFamily: "'Nunito', sans-serif"
          }}
        >
          {userName}
        </span>
      </div>
      
      {/* Invite button */}
      <button
        onClick={handleInvite}
        className="absolute flex items-center justify-center gap-2 transition-all duration-200 active:opacity-80"
        style={{
          top: '12px',
          right: '16px',
          height: '36px',
          padding: '0 14px',
          background: 'linear-gradient(135deg, #E88B72 0%, #D87C68 100%)',
          borderRadius: '10px',
          fontSize: '12px',
          color: '#FFFFFF',
          fontWeight: 600,
          border: 'none',
          cursor: 'pointer',
          boxShadow: '0 2px 6px rgba(0,0,0,0.15)',
          fontFamily: "'Nunito', sans-serif"
        }}
      >
        <span>{isCopied ? "Скопировано" : "Пригласить друга"}</span>
        {isCopied ? (
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2.5">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        ) : (
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
          </svg>
        )}
      </button>

      {/* Top Banner Card */}
      <div 
        className="absolute left-4 right-4 rounded-2xl overflow-hidden"
        style={{ 
          top: '56px',
          background: 'linear-gradient(135deg, #E19477 0%, #D68B6F 100%)',
          padding: '20px 16px',
          minHeight: '180px',
          boxShadow: '0 4px 16px rgba(0,0,0,0.1)'
        }}
      >
        <div className="flex items-center justify-between h-full relative">
          <div className="flex flex-col gap-2 z-10">
            {/* БУДЬ ПЕРВЫМ В ТОПЕ */}
            <h2 
              style={{
                fontSize: '20px',
                fontWeight: 900,
                color: '#FFE8D9',
                textTransform: 'uppercase',
                lineHeight: 1.2,
                letterSpacing: '0.5px',
                textShadow: '0 2px 4px rgba(0,0,0,0.15)',
                marginBottom: '8px',
                fontFamily: "'Nunito', sans-serif"
              }}
            >
              БУДЬ ПЕРВЫМ<br />В ТОПЕ
            </h2>
            
            {/* Кнопка ПОЛУЧАЙ 5000 ₽ */}
            <div 
              style={{
                background: 'linear-gradient(135deg, #F39C6B 0%, #E88B72 100%)',
                borderRadius: '12px',
                padding: '10px 24px',
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                boxShadow: '0 3px 8px rgba(0,0,0,0.15)',
                width: 'fit-content',
                marginBottom: '6px'
              }}
            >
              <span 
                style={{
                  fontSize: '16px',
                  fontWeight: 700,
                  color: '#FFFFFF',
                  whiteSpace: 'nowrap',
                  fontFamily: "'Nunito', sans-serif"
                }}
              >
                ПОЛУЧАЙ 1500 ₽
              </span>
            </div>
            
            {/* Подтекст */}
            <p 
              style={{
                fontSize: '12px',
                fontWeight: 400,
                color: 'rgba(255, 255, 255, 0.85)',
                lineHeight: 1.3,
                fontFamily: "'Nunito', sans-serif"
              }}
            >
              подарочной картой<br />каждый месяц
            </p>
          </div>
          
          {/* Заяц */}
          <img 
            src={leaderboardBunny}
            alt="5000₽ Bunny"
            style={{ 
              width: '140px',
              height: 'auto',
              position: 'absolute',
              right: '-5px',
              bottom: '0',
              zIndex: 5
            }}
          />
        </div>
      </div>

      {/* Leaders Title */}
      <h3 
        className="absolute left-0 right-0 text-center"
        style={{
          top: '260px',
          fontSize: '18px',
          fontWeight: 800,
          color: '#FFFFFF',
          textTransform: 'uppercase',
          letterSpacing: '1.5px',
          textShadow: '0 2px 4px rgba(0,0,0,0.1)',
          fontFamily: "'Nunito', sans-serif"
        }}
      >
        ТОП ИГРОКОВ
      </h3>

      {/* Leaders List */}
      <div 
        className="absolute left-4 right-4 overflow-y-auto"
        style={{ 
          top: '295px',
          bottom: '80px',
          paddingBottom: '10px',
          scrollBehavior: 'smooth',
          WebkitOverflowScrolling: 'touch'
        }}
      >
        {leaders.map((leader, index) => (
          <div 
            key={leader.rank}
            className="flex items-center gap-3 mb-3 px-4 py-3 rounded-xl animate-fade-in"
            style={{
              background: 'rgba(255, 230, 215, 0.65)',
              animationDelay: `${index * 0.05}s`,
              backdropFilter: 'blur(8px)',
              boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
            }}
          >
            {/* Avatar with rank */}
            <div className="relative flex-shrink-0">
              <div 
                className="w-12 h-12 rounded-full overflow-hidden"
                style={{ 
                  border: '2px solid #E8A68A',
                  background: '#A8D5BA'
                }}
              >
                <img 
                  src={`https://i.pravatar.cc/80?img=${leader.rank}`} 
                  alt={leader.name}
                  className="w-full h-full object-cover"
                />
              </div>
              <div 
                className="absolute -bottom-1 -right-1 w-6 h-6 rounded-full flex items-center justify-center"
                style={{
                  background: leader.rank <= 3 ? '#E88B72' : '#D4896E',
                  color: 'white',
                  fontSize: '11px',
                  fontWeight: 700,
                  border: '2px solid white',
                  fontFamily: "'Nunito', sans-serif"
                }}
              >
                {leader.rank}
              </div>
            </div>

            {/* Name */}
            <div className="flex-1 min-w-0">
              <span 
                style={{
                  fontSize: '15px',
                  fontWeight: 600,
                  color: '#8B5A47',
                  fontFamily: "'Nunito', sans-serif"
                }}
              >
                {leader.name}
              </span>
            </div>

            {/* Referrals */}
            <div className="flex items-center gap-1 flex-shrink-0">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" strokeWidth="2.5">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
              <span 
                style={{
                  fontSize: '14px',
                  color: '#FFFFFF',
                  fontWeight: 600,
                  fontFamily: "'Nunito', sans-serif"
                }}
              >
                {leader.referrals}
              </span>
            </div>

            {/* Prize */}
            <div 
              className="px-4 py-2 rounded-xl flex-shrink-0"
              style={{
                background: 'linear-gradient(135deg, #E88B72 0%, #D87C68 100%)',
                color: 'white',
                fontSize: '13px',
                fontWeight: 700,
                boxShadow: '0 2px 6px rgba(0,0,0,0.15)',
                fontFamily: "'Nunito', sans-serif"
              }}
            >
              {leader.prize}
            </div>
          </div>
        ))}
      </div>

      <BottomNav />
    </div>
  );
};

export default Leaderboard;
