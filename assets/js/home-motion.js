(function(){
  'use strict';
  var year=document.getElementById('home-year');
  if(year)year.textContent=String(new Date().getFullYear());

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
          var start=(index/missionWords.length)*.82;
          var wordProgress=Math.max(0,Math.min(1,(progress-start)/.18));
          var eased=wordProgress*wordProgress*(3-2*wordProgress);
          word.style.opacity=String(.13+(.87*eased));
          word.style.transform='translateY('+((1-eased)*.16)+'em)';
        });
      }
      ticking=false;
    }
    window.addEventListener('scroll',function(){if(!ticking){window.requestAnimationFrame(updateMotion);ticking=true;}},{passive:true});
    window.addEventListener('resize',function(){if(!ticking){window.requestAnimationFrame(updateMotion);ticking=true;}});
    updateMotion();
  }
}());
