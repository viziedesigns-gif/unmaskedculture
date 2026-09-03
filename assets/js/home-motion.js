(function(){
  'use strict';
  var year=document.getElementById('home-year');
  if(year)year.textContent=String(new Date().getFullYear());

  var newsletterStatus=document.getElementById('newsletter-status');
  if(newsletterStatus){
    var newsletterResult=new URLSearchParams(window.location.search).get('newsletter');
    var newsletterMessages={
      success:'You’re subscribed. Welcome to the Unmasked community.',
      validation:'Please enter a valid email address and try again.',
      config:'Newsletter signup is temporarily unavailable. Please try again later.',
      auth:'Newsletter signup could not be authorized. Please try again later.',
      payload:'We could not accept that signup. Please review your information and try again.',
      form:'The newsletter form is temporarily unavailable. Please try again later.',
      rate:'Too many signup attempts were received. Please wait a moment and try again.',
      connection:'We could not connect to the newsletter service. Please try again shortly.',
      formcan:'We could not complete your signup. Please try again.'
    };
    if(newsletterResult&&newsletterMessages[newsletterResult]){
      newsletterStatus.textContent=newsletterMessages[newsletterResult];
      newsletterStatus.classList.add(newsletterResult==='success'?'is-success':'is-error');
      newsletterStatus.hidden=false;
      newsletterStatus.focus({preventScroll:true});
    }
  }

  var reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var missionScroll=document.querySelector('[data-mission-scroll]');
  var missionWords=[];
  if(missionScroll){
    var missionText=missionScroll.querySelector('.home-mission-statement__text');
    if(missionText){
      var words=missionText.textContent.trim().split(/\s+/);
      missionText.textContent='';
      words.forEach(function(word,index){
        var span=document.createElement('span');
        span.className='home-mission-word';
        span.textContent=word;
        missionText.appendChild(span);
        if(index<words.length-1)missionText.appendChild(document.createTextNode(' '));
        missionWords.push(span);
      });
    }
  }
  var reveals=document.querySelectorAll('[data-reveal]');
  if(reduced||!('IntersectionObserver' in window)){
    reveals.forEach(function(node){node.classList.add('is-visible');});
    missionWords.forEach(function(word){word.classList.add('is-active');});
  }else{
    var observer=new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if(entry.isIntersecting){entry.target.classList.add('is-visible');observer.unobserve(entry.target);}
      });
    },{threshold:.12,rootMargin:'0px 0px -7% 0px'});
    reveals.forEach(function(node){observer.observe(node);});

    var heroMedia=document.querySelector('.home-hero__media video');
    var ticking=false;
    function updateMotion(){
      if(heroMedia){var offset=Math.min(window.scrollY*.055,42);heroMedia.style.transform='translate3d(0,'+offset+'px,0) scale(1.03)';}
      if(missionScroll&&missionWords.length){
        var rect=missionScroll.getBoundingClientRect();
        var travel=Math.max(1,rect.height-window.innerHeight);
        var progress=Math.max(0,Math.min(1,-rect.top/travel));
        missionWords.forEach(function(word,index){
          var start=(index/missionWords.length)*.74;
          var wordProgress=Math.max(0,Math.min(1,(progress-start)/.26));
          var eased=wordProgress*wordProgress*(3-2*wordProgress);
          word.style.opacity=String(.12+(.88*eased));
          word.style.filter='blur('+((1-eased)*9)+'px) brightness('+(.55+(.45*eased))+')';
          word.style.transform='translateY('+((1-eased)*.22)+'em) scale('+(0.985+(.015*eased))+')';
        });
      }
      ticking=false;
    }
    window.addEventListener('scroll',function(){if(!ticking){window.requestAnimationFrame(updateMotion);ticking=true;}},{passive:true});
    window.addEventListener('resize',function(){if(!ticking){window.requestAnimationFrame(updateMotion);ticking=true;}});
    updateMotion();
  }
}());
