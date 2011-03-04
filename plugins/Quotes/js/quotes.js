function Gdn_Quotes() {

   this.InsertMode = 'default';  
   this.Editors = [];
   this.EditorID = 1;
   
   Gdn_Quotes.prototype.Prepare = function() {
   
      // Attach quote event to each Quote button, and return false to prevent link follow
      $('span.CommentQuote a').livequery('click', jQuery.proxy(function(event){
         var QuoteLink = $(event.target);
         var ObjectID = QuoteLink.attr('href').split('/').pop();
         this.Quote(ObjectID, QuoteLink);
         return false;
      },this));
            
      /**
       * Always track which editor we're looking at
       *  - Follow clicks to Edit button
       *  - Follow clicks to Submits and Cancels
       */
       
      // Follow edit clicks
      var Quotes = this;
      $('textarea.TextBox').livequery(function(){
         Quotes.EditorStack(this);
      }, function(){
         Quotes.EditorStack(this, true);
      });
      
      // Determine what mode we're in (default, cleditor... ?)
      $('div.cleditorMain').livequery(function(){
         Quotes.SetInsertMode('cleditor', this);
      });
   }
   
   Gdn_Quotes.prototype.SetInsertMode = function(InsertMode, ChangeElement) {
      var OldInsert = this.InsertMode;
      var Changed = (OldInsert == InsertMode);
      this.InsertMode = InsertMode;
      
      switch (this.InsertMode) {
         case 'cleditor':

            var Frame = $($(ChangeElement).find('textarea.TextBox').get(0).editor.$frame).get(0);
            var FrameBody = null;
            var FrameDocument = null;
            
            // DOM
            if (Frame.contentDocument) {
               FrameDocument = Frame.contentDocument;
               FrameBody = FrameDocument.body;
            // IE
            } else if (Frame.contentWindow) {
               FrameDocument = Frame.contentWindow.document;
               FrameBody = FrameDocument.body;
            }
            
            if (FrameBody == null) return;
            
            /*
            console.log(FrameDocument.getElementsByTagName('head')[0]);
            
            // make a new stylesheet
            var NewStyle = FrameDocument.createElement('style');
            FrameDocument.getElementsByTagName('head')[0].appendChild(NewStyle);
            
            // Safari does not see the new stylesheet unless you append something.
            // However!  IE will blow chunks, so ... filter it thusly:
            if (!window.createPopup) {
               console.log('appending');
               NewStyle.appendChild(FrameDocument.createTextNode(''));
            }
            
            var Style = FrameDocument.styleSheets[FrameDocument.styleSheets.length - 1];
            console.log(Style);
            // some rules to apply
            var Rules = {
               "blockquote" : "{ color: red; padding: 5px; }"
            }
            
            // loop through and insert
            for (Selector in Rules) {
               if (Style.insertRule) {
                  // it's an IE browser
                  try { 
                     console.log('insertrule');
                     Style.insertRule(Selector + Rules[Selector], 0); 
                  } catch(e) { console.log(e); }
               } else {
                  // it's a W3C browser
                  try {
                     console.log('addrule');
                     Style.addRule(Selector, Rules[Selector]); 
                  } catch(e) { console.log(e); } 
               }               
            }
*/
				var webRoot = gdn.definition('WebRoot', '');
            var ss = document.createElement("link");
            ss.type = "text/css";
            ss.rel = "stylesheet";
            ss.href = gdn.combinePaths(webRoot, '/plugins/Quotes/css/cleditor.css');
            
            if (document.all)
            	FrameDocument.createStyleSheet(ss.href);
            else
            	FrameDocument.getElementsByTagName("head")[0].appendChild(ss);

         break;
         
         case 'default':
         default:
            // Nothing for now
         break;
      }
   }
   
   Gdn_Quotes.prototype.GetObjectID = function(Anchor) {
      return $(Anchor).attr('href').split('/').pop();
   }
   
   Gdn_Quotes.prototype.EditorStack = function(AreaContainer, Remove) {
      if (AreaContainer == undefined) return false;
      
      var TextArea = null;
      if ($(AreaContainer).get(0).nodeName.toLowerCase() == 'textarea')
         TextArea = $(AreaContainer);
      else {
         TextArea = $(AreaContainer).find('textarea.TextBox');
         if (TextArea.length == 0) return false;
      }
   
      if (Remove == undefined || Remove == false) {
         // Add an editor
         if (TextArea.length) {
            TextArea.get(0).eid = this.EditorID++;
            this.Editors.push(TextArea);
         }
      } else {
         var EID = TextArea.get(0).eid;
         
         // Get rid of an editor
         $(this.Editors).each(jQuery.proxy(function(i,el){
            if (el.get(0).eid == EID) {
               this.Editors.splice(i,1);
               return;
            }
         },this));
      }
      
      return true;
   }
   
   Gdn_Quotes.prototype.GetEditor = function() {
      return this.Editors[this.Editors.length-1];
   }
   
   Gdn_Quotes.prototype.Quote = function(ObjectID, QuoteLink) {
      var QuotingStatus = this.GetQuoteData(ObjectID);
      if (!QuotingStatus) return;
      
      switch (this.InsertMode) {
         case 'cleditor':
            var ScrollY = $(this.GetEditor().get(0).editor.$frame).offset().top - 100; // 100 provides buffer in viewport
         break;
         
         case 'default':
         default:
            var ScrollY = this.GetEditor().offset().top - 100; // 100 provides buffer in viewport
         break;
      }
      
      $('html,body').animate({scrollTop: ScrollY}, 800);
   }
   
   Gdn_Quotes.prototype.GetQuoteData = function(ObjectID) {
      var QuotedElement = $('#'+ObjectID);
      if (!QuotedElement) return false;
      
      this.AddSpinner();
      var QuotebackURL = gdn.url('plugin/quotes/getquote/'+ObjectID);
      jQuery.ajax({
         url: QuotebackURL,
         type: 'GET',
         success: jQuery.proxy(this.QuoteResponse,this)
      });
      return true;
   }
   
   Gdn_Quotes.prototype.AddSpinner = function() {
      
   }
   
   Gdn_Quotes.prototype.RemoveSpinner = function() {
      
   }
   
   Gdn_Quotes.prototype.QuoteResponse = function(Data, Status, XHR) {
      Data = jQuery.parseJSON(Data);
      if (Data && Data.Quote.selector) {
         var ObjectID = Data.Quote.selector;
         this.RemoveSpinner();
      } else { return; }
      
      switch (Data.Quote.format) {
         case 'Html':   // HTML
            var Append = '<blockquote rel="'+Data.Quote.authorname+'">'+Data.Quote.body+'</blockquote>'+"\n";
            break;
            
         case 'BBCode':
            var Append = '[quote="'+Data.Quote.authorname+'"]'+Data.Quote.body+'[/quote]'+"\n";
            break;
         
         case 'Display':
         case 'Text':   // Plain
            var Append = ' > '+Data.Quote.authorname+" said:\n";
            Append = Append+' > '+Data.Quote.body+"\n";
            break;
            
         default:
            var Append = '';
            return;
      
      }
      
      this.ApplyQuoteText(Append);
   }
   
   Gdn_Quotes.prototype.ApplyQuoteText = function(QuoteText) {
      QuoteText = QuoteText+"\n";
      var Editor = this.GetEditor();
      Editor.val(Editor.val() + QuoteText);
      
      switch (this.InsertMode) {
         case 'cleditor':
            Editor.val(Editor.val() + "<br/>");
            Editor.get(0).editor.updateFrame();
         break;
         
         case 'default':
         default:
            // Do nothing special
         break;
      }
   }
   
}

var GdnQuotes = null;
jQuery(document).ready(function(){
   GdnQuotes = new Gdn_Quotes();
   GdnQuotes.Prepare()
});