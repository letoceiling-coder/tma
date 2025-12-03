import { useState, memo } from "react";
import { useNavigate } from "react-router-dom";
import { haptic } from "@/lib/haptic";

// Import background
import floralBg from "@/assets/how-to-play/floral-bg.png";

// Import slide 1 assets
import bunnyGifts from "@/assets/how-to-play/bunny-gifts-new.png";
import btnWin from "@/assets/how-to-play/btn-win.png";
import btnOt300 from "@/assets/how-to-play/btn-ot300.png";
import btnDo1500 from "@/assets/how-to-play/btn-do1500.png";
import btnWowbox from "@/assets/how-to-play/btn-wowbox.png";
import btnSecretbox from "@/assets/how-to-play/btn-secretbox.png";

// Import slide 2 assets
import bunniesPodium from "@/assets/how-to-play/bunnies-podium-new.png";
import btnInvite from "@/assets/how-to-play/btn-invite.png";
import btnTickets from "@/assets/how-to-play/btn-tickets.png";

// Import slide 3 assets
import bunnyTrophy from "@/assets/how-to-play/bunny-trophy-new.png";
import btnZovi from "@/assets/how-to-play/btn-zovi.png";
import btnTop from "@/assets/how-to-play/btn-top.png";
import btnMore from "@/assets/how-to-play/btn-more.png";
import prize1 from "@/assets/how-to-play/prize-1.png";
import prize2 from "@/assets/how-to-play/prize-2.png";
import prize3 from "@/assets/how-to-play/prize-3.png";

// Slide 1 - Custom design with assets
const Slide1 = memo(() => (
  <section className="flex flex-col items-center w-full h-full px-[4vw] justify-between pb-[70px] relative">
    {/* Top content */}
    <div className="flex flex-col items-center w-full pt-[2vh] relative z-10">
      {/* ВЫИГРЫВАЙ button */}
      <img 
        src={btnWin} 
        alt="Выигрывай"
        loading="eager"
        className="w-[80vw] max-w-[320px] h-auto mb-[1vh]"
      />

      {/* ДО 1500 ₽ text */}
      <h1 className="text-[14vw] font-extrabold text-white text-center m-0 mb-[1.5vh] tracking-tight"
        style={{
          fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
          textShadow: '0 4px 16px rgba(0,0,0,0.25)'
        }}
      >
        ДО 1500 ₽
      </h1>

      {/* Tag buttons - rows */}
      <div className="flex flex-col items-center gap-[1.2vh] w-full">
        {/* Row 1 - two buttons */}
        <div className="flex justify-center gap-[3vw] w-full">
          <img src={btnOt300} alt="Призы от 300 ₽" loading="lazy" className="h-[5.5vh] max-h-[44px] w-auto" />
          <img src={btnDo1500} alt="До 1500 ₽" loading="lazy" className="h-[5.5vh] max-h-[44px] w-auto" />
        </div>
        <img src={btnWowbox} alt="Подарки от партнера IWOWWE" loading="lazy" className="h-[5.5vh] max-h-[44px] w-auto" />
        <img src={btnSecretbox} alt="Секретный бокс" loading="lazy" className="h-[5.5vh] max-h-[44px] w-auto" />
      </div>
    </div>

    {/* Bunny with gifts image */}
    <div className="flex-1 flex items-end justify-center w-full overflow-visible">
      <img 
        src={bunnyGifts} 
        alt="Кролик с подарками"
        loading="eager"
        className="w-[200%] max-w-[900px] h-auto object-contain"
        style={{ maxHeight: '90vh', marginBottom: '-40px' }}
      />
    </div>
  </section>
));

Slide1.displayName = 'Slide1';

// Slide 2 - Invite friends design
const Slide2 = memo(() => (
  <section className="flex flex-col items-center w-full h-full px-[4vw] justify-end pb-[70px]">
    {/* Top content */}
    <div className="flex flex-col items-center w-full mb-auto pt-[2vh]">
      {/* ПРИГЛАШАЙ ДРУЗЕЙ button */}
      <img 
        src={btnInvite} 
        alt="Приглашай друзей"
        loading="eager"
        className="w-[85vw] max-w-[340px] h-auto mb-[1vh]"
      />

      {/* Ampersand symbol */}
      <span
        className="text-[14vw] font-light text-white italic leading-none mb-[1vh]"
        style={{
          fontFamily: "Georgia, 'Times New Roman', serif",
          textShadow: '0 2px 10px rgba(0,0,0,0.15)'
        }}
      >
        &amp;
      </span>

      {/* ПОЛУЧАЙ БИЛЕТЫ button */}
      <img 
        src={btnTickets} 
        alt="Получай билеты"
        loading="eager"
        className="w-[85vw] max-w-[340px] h-auto"
      />
    </div>

    {/* Bunnies on podium image */}
    <div className="flex items-end justify-center w-full overflow-visible" style={{ flex: '1 1 65%', minHeight: '50vh' }}>
      <img 
        src={bunniesPodium} 
        alt="Кролики на пьедестале"
        loading="eager"
        className="w-[110%] max-w-[480px] h-auto object-contain"
        style={{ maxHeight: '68vh', marginBottom: '-10px' }}
      />
    </div>
  </section>
));

Slide2.displayName = 'Slide2';

// Slide 3 - Prize system design
const Slide3 = memo(() => (
  <section className="flex flex-col items-center w-full h-full px-[4vw] justify-end pb-[70px] relative">
    {/* Top content - All buttons and prizes */}
    <div className="flex flex-col items-center gap-[1.5vh] w-full mb-auto pt-[2vh]">
      <img 
        src={btnZovi} 
        alt="Зови друзей"
        loading="eager"
        className="w-[90vw] max-w-[360px] h-auto"
      />
      <img 
        src={btnTop} 
        alt="Занимай топ"
        aria-label="Занимай топ — перейти к участию в рейтинге"
        loading="eager"
        className="w-[90vw] max-w-[360px] h-auto"
      />
      <img 
        src={btnMore} 
        alt="Получай больше подарков"
        loading="eager"
        className="w-[90vw] max-w-[360px] h-auto"
      />
      <img src={prize1} alt="1 место = 1500 ₽" loading="lazy" className="w-[90vw] max-w-[360px] h-auto mt-[0.5vh]" />
      <img src={prize2} alt="2 место = 1000 ₽" loading="lazy" className="w-[90vw] max-w-[360px] h-auto" />
      <img src={prize3} alt="3 место = 500 ₽" loading="lazy" className="w-[90vw] max-w-[360px] h-auto" />
    </div>

    {/* Bunny with trophy */}
    <div className="flex items-end justify-center w-full relative overflow-visible" style={{ flex: '1 1 55%', minHeight: '40vh' }}>
      {/* Soft glow circle behind bunny */}
      <div 
        className="absolute bottom-[0%] left-1/2 -translate-x-1/2 w-[100vw] h-[100vw] max-w-[400px] max-h-[400px] rounded-full"
        style={{
          background: 'radial-gradient(circle, rgba(245,180,150,0.6) 0%, rgba(245,180,150,0) 70%)',
          zIndex: 0
        }}
      />
      <img 
        src={bunnyTrophy} 
        alt="Кролик с кубком"
        loading="eager"
        className="w-[90%] max-w-[380px] h-auto object-contain relative z-10"
        style={{ 
          maxHeight: '52vh',
          marginBottom: '-10px',
          filter: 'drop-shadow(0 10px 25px rgba(0,0,0,0.18))'
        }}
      />
    </div>
  </section>
));

Slide3.displayName = 'Slide3';

const HowToPlay = () => {
  const navigate = useNavigate();
  const [currentSlide, setCurrentSlide] = useState(0);
  const totalSlides = 3;

  const handleNext = () => {
    haptic.lightTap();
    if (currentSlide < totalSlides - 1) {
      setCurrentSlide(currentSlide + 1);
    } else {
      navigate(-1);
    }
  };

  const handlePrev = () => {
    haptic.lightTap();
    if (currentSlide > 0) {
      setCurrentSlide(currentSlide - 1);
    } else {
      navigate(-1);
    }
  };

  return (
    <div 
      className="fixed inset-0 z-50 overflow-hidden flex flex-col"
      style={{
        background: 'linear-gradient(180deg, #FCDAC6 0%, #F8B89A 100%)',
        fontFamily: "-apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif",
        minHeight: '100dvh'
      }}
    >
      {/* Floral background overlay with 50% opacity */}
      <div
        className="absolute inset-0 pointer-events-none"
        style={{
          backgroundImage: `url(${floralBg})`,
          backgroundSize: 'cover',
          backgroundPosition: 'center',
          backgroundRepeat: 'no-repeat',
          opacity: 0.5,
          zIndex: 0
        }}
      />

      {/* Close button */}
      <button
        onClick={() => {
          haptic.lightTap();
          navigate(-1);
        }}
        aria-label="Закрыть"
        className="absolute top-3 right-3 w-9 h-9 bg-white/40 border-none rounded-full cursor-pointer flex items-center justify-center z-[100] active:scale-95 transition-transform"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M18 6L6 18M6 6L18 18" stroke="#333" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      </button>

      {/* Content area */}
      <div className="w-full flex-1 flex items-stretch justify-center relative z-[1]">
        {currentSlide === 0 && <Slide1 />}
        {currentSlide === 1 && <Slide2 />}
        {currentSlide === 2 && <Slide3 />}
      </div>

      {/* Pagination footer */}
      <nav className="absolute bottom-0 left-0 right-0 h-[65px] flex items-center justify-between px-5 pb-3 z-10">
        {/* Left arrow */}
        <button
          onClick={handlePrev}
          aria-label="Предыдущий слайд"
          className="w-11 h-11 bg-white/30 border-none rounded-full cursor-pointer flex items-center justify-center active:scale-95 transition-transform"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
            <path d="M15 18L9 12L15 6" stroke="#333" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
          </svg>
        </button>

        {/* Dots indicator */}
        <div className="flex gap-2.5 items-center justify-center">
          {[0, 1, 2].map((index) => (
            <button
              key={index}
              onClick={() => {
                haptic.selection();
                setCurrentSlide(index);
              }}
              aria-label={`Слайд ${index + 1}`}
              className="border-none cursor-pointer p-0 transition-all duration-200"
              style={{
                width: currentSlide === index ? '10px' : '8px',
                height: currentSlide === index ? '10px' : '8px',
                borderRadius: '50%',
                background: currentSlide === index ? '#333' : 'rgba(255,255,255,0.5)'
              }}
            />
          ))}
        </div>

        {/* Right arrow */}
        <button
          onClick={handleNext}
          aria-label="Следующий слайд"
          className="w-11 h-11 bg-white/30 border-none rounded-full cursor-pointer flex items-center justify-center active:scale-95 transition-transform"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
            <path d="M9 18L15 12L9 6" stroke="#333" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
          </svg>
        </button>
      </nav>
    </div>
  );
};

export default HowToPlay;
